# ADR 0015 — Employer erasure (GDPR)

Status: Accepted (2026-11-16, lead-accepted: the recommendations of the lot 10 design and the lead decisions of
2026-11-16 are binding). Relates to [ADR 0003](0003-retry-and-unknown-outcome.md),
[ADR 0006](0006-local-lifecycles.md), [ADR 0007](0007-personal-data.md), [ADR 0009](0009-guards-and-opt-outs.md),
[ADR 0010](0010-tenant-model.md), [ADR 0012](0012-integration-events.md), [ADR 0013](0013-symfony-bridge.md) and
[ADR 0014](0014-doctrine-bridge.md).

## Context

The package stores personal data (the employer birth date, the declaration result amounts, URSSAF descriptions) and
ADR 0007 leaves the retention to the host. Hosts need a supported way to erase an employer from the package store
without writing SQL against the bridge tables. The erasure was deferred twice: the console command needed an
Application use case (ADR 0013 decision 14), and the mandate port had no `delete()` (ADR 0014 decision 12).

The package does not decide *when* data may be erased: that is the host's legal policy. It only refuses, by default,
the erasures that would break **its own workflow guarantees**: a call in flight, an outcome nobody settled, a mandate
still active at URSSAF.

## Decision

1. **A use case, nothing sent to URSSAF.** `ForgetEmployer` (handled by `ForgetEmployerHandler`) erases, in **one**
   `UnitOfWork` transaction, the employer account, every mandate of the employer and its declaration records in the
   erasable states. Records and mandates are linked to the employer by `(tenant, Cesu number)`, not by account id. No
   gateway is injected: cancelling the mandate at URSSAF (`CancelMandate`, method 031) is the host's separate choice,
   made **before** the erasure. URSSAF keeps its own data.
2. **Order and atomicity.** The handler locates the account inside the transaction, checks the refusal rules **before
   any deletion**, deletes the records, then the mandates (and their active-mandate key row in the Doctrine bridge),
   then the account **last**, and re-checks that nothing of the employer is left
   (`employer_account.concurrent_modification` and a rollback otherwise). With the non-transactional in-memory
   `ImmediateTransactionManager` (standalone default) nothing rolls back: the refusals happen before any deletion and
   the post-check cannot fire in-process.
3. **Refusal rules** (`EmployerErasureRefused`, never a legal rule):

   | Found for `(tenant, Cesu number)` | Default | Opt-in |
   |---|---|---|
   | record `submitting` | refused `employer_account.forget_submission_in_flight` | none |
   | record `outcome_unknown` | refused `employer_account.forget_unknown_outcome` | `allowUnknownOutcomes` |
   | mandate `notified` or `confirmed` | refused `employer_account.forget_active_mandate` | `allowActiveMandate` |
   | record `pending`, `error`, `invalid_credentials` | erased | n/a |
   | record `declared`, `rejected`, `already_declared`; mandate `cancelled` | erased | n/a |

   - `submitting` is **always** refused: a 050 call may be running, and erasing races a live HTTP call whose outcome
     would be lost. The state is transient (a crash is moved to `outcome_unknown` by `ReconcileStaleDeclarations`,
     ADR 0003): wait, then retry.
   - `outcome_unknown`: ADR 0003 asks an operator to settle it (`ResolveDeclarationOutcome`); erasing it loses the
     only local trace that prevents a re-send of the same external reference.
   - Active mandate: it stays active **at URSSAF**. The expected order is settle unknown outcomes, `CancelMandate`,
     then `ForgetEmployer`.
   - Only mandates the package notified (non-cancelled local `Mandate`) are considered: for an employer mandated
     outside the package (ADR 0009 amendment: no local mandate, stage `mandate_confirmed` or `aici_active`) check the
     mandate at URSSAF yourself.
   - Check order when several refusals apply: active mandate, then `submitting`, then `outcome_unknown`.
4. **Configurable policy (lead decision).** The defaults of the two opt-ins come from `WorkflowPolicy`
   (`allowErasureWithUnknownOutcomes`, `allowErasureWithActiveMandate`, both `false`), exposed as
   `workflow.erasure.allow_unknown_outcomes` and `workflow.erasure.allow_active_mandate` in the Symfony bundle and
   through `withPolicy()` in the standalone builder. The `ForgetEmployer` fields `allowActiveMandate` /
   `allowUnknownOutcomes` are nullable: `null` uses the policy, `true` / `false` is an explicit per-call choice. The
   console options `--allow-active-mandate` / `--allow-unknown-outcomes` set `true`; without them the policy applies.
   `--force` only skips the confirmation: a cron running `--force` never overrides a safety default.
5. **Ports.** `MandateRepository::delete(MandateId)` (in the Doctrine bridge: detaches, deletes the row and the key row
   of `urssaf_cesu_active_mandates`, so that the employer can hold a new active mandate afterwards),
   `DeclarationRecordRepository::countByEmployer()` (count per state) and `DeclarationRecordRepository::deleteByEmployer()`
   (deletes the records whose state is in a given list and returns their ids, oldest first: a record claimed
   concurrently, no longer in the list, survives and the post-check rolls back). Counts and lists come from the
   selected ids, never from the affected-row count (ADR 0014 decision 10). No schema change, no index on
   `employer_cesu_number` (hosts that erase often add one in their migration).
6. **Event (lead decision).** `EmployerAccount::forget()` records the domain event `EmployerForgotten`, translated by
   the `UnitOfWork` into the integration event `urssaf.cesu.employer.forgotten` (VERSION 1) carrying the Cesu number,
   the erased mandate ids and the erased declaration record ids (identifiers only, no birth date). It is appended to
   the outbox in the same transaction and published after the commit. Hosts erase their own copies from it.
7. **Outbox untouched (lead decision).** No `Outbox` port change and no purge of the employer entries: payloads are
   identifiers, states and codes only (ADR 0012). Dispatched entries are deleted by `PurgeOutbox` after
   `outbox.retention` (default `P30D`); pending entries stay until delivered (monitor the relay, purge earlier if
   your policy requires it). A mandate or declaration event of a listed aggregate delivered **after**
   `employer.forgotten` (an earlier delivery failed) must be ignored by the host, not used to recreate a copy.
8. **Console command.** `urssaf:cesu:employer:forget` (Symfony bridge): employer selector, tenant, `--force`,
   `--allow-active-mandate`, `--allow-unknown-outcomes`; confirmation unless `--force` (required when not
   interactive). Exit `0` erased or aborted, `1` refusal / not found / concurrent modification, `2` invalid usage. The
   output holds the account id and counts only. No lock, no `--dry-run`, no Messenger message (a one-off operator
   action already serialized by the transaction).
9. **Second run.** An erased employer answers `employer_account.not_found` (exit `1`), not a silent no-op: a wrong
   selector must not be hidden.
10. **Out of scope.** Untracked employers (records declared with `skipMandateCheck` and no account) are not covered
    (`employer_account.not_found`): use `deleteByEmployer()` in your own transaction. A residual race is accepted: a
    **new** record of the employer inserted by a concurrent `DeclareSalary` (only possible with `skipMandateCheck` or
    `require_local_mandate: false`) between the SELECT and the DELETE of `deleteByEmployer()` may be erased without
    being listed in the event; nothing was sent for it. Stop declaring for an employer before erasing it.

## Consequences

- Idempotency of `DeclareSalary` is lost for the erased external references: re-sending one declares it again at
  URSSAF.
- The ADR 0009 memory "a local mandate once existed" is reset: a later import of the same employer with a confirmed
  stage passes the guard again.
- URSSAF keeps its data and the mandate; backups are the host's concern (`docs/doctrine.md`).
- Two repository ports gained methods: custom repositories (`custom` driver) must implement them (breaking
  change of an unreleased package, noted in the changelog).
- Documented in `docs/workflow.md`, `docs/doctrine.md`, `docs/integration-events.md`, `docs/symfony.md` and
  `docs/standalone.md`.
