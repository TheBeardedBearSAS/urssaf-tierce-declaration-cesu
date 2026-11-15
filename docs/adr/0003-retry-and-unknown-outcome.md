# ADR 0003 — Retries and unknown outcomes

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

CESU has no idempotency key, no GET for a declaration and no way to find an employer from its registration data
(api-reference §4.5). The reference reconciles stale payment requests through a search (method 070); here nothing
equivalent exists. A blind resend of 050 risks a duplicate declaration (rejected by the server duplicate rule, losing
the first `idDeclaration`), and a resend of 010 hits the e-mail uniqueness check.

## Decision

- Safe to retry (`RetryableException`): 020, 021, 040 (reads), 030 and 031 (*assumed* idempotent, §5.17), the token
  call, and 429 on every operation.
- Never resent automatically: 010, 050, 060, 070. A transport error or a 5xx on them throws `OutcomeUnknown`
  (`UrssafUnavailable::mayHaveReachedUrssaf()` is true). 429 and a local rate-limit refusal are safe to retry because
  the call provably did not reach the service.
- Declarations: an unknown outcome moves the local declaration to `outcome_unknown`; `ReconcileStaleDeclarations`
  moves declarations left `submitting` past `stale_submission_after` to `outcome_unknown` with outcome
  `manual_review`. An operator checks on cesu.urssaf.fr, then records the result (`ResolveDeclarationOutcome`:
  declared with its `idDeclaration`, or not received → `pending` again, then resent by the host calling
  `DeclareSalary` with the same declaration).
- The duplicate business error on 050 ("Une déclaration est déjà enregistrée pour cette période d’emploi.") maps to
  `already_declared` (final, no `idDeclaration`).
- Messenger: `OutcomeUnknown` and every non-retryable package exception become unrecoverable; `RetryableException`
  becomes recoverable with its delay.

## Consequences

- No automatic duplicate is ever produced by the package.
- Some declarations need a manual action; `GetWorkflowOverview` counts them under `needingAttention()`.
