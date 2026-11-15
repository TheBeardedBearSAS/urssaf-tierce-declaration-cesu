# ADR 0006 — Local lifecycles of employers, mandates and declarations

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

URSSAF exposes no status for declarations and only the 020 snapshot for employers (api-reference §4.3, §4.4). The
reference drives registrations and payment requests through explicit state machines with claims, attempts and
reconciliation.

## Decision

- `Domain\Employer\EmployerAccount` (aggregate, per tenant and `numeroCesu`): keeps the last `EmployerStatus`
  snapshot (020) with the time it was read, the registration origin (registered through 010 or imported) and the
  derived `EmployerStage`: `registered` → `mandate_notified` (030) → `mandate_confirmed` (020 `mandatPresent`) →
  `aici_active`; `mandate_cancelled` after 031. Stages follow 020, they never block a call. Cesu+ is a state of an
  employer/employee **pair** (020 `etatCesuPlus`), so it is not an employer stage: the snapshot keeps the reference kind and,
  when known, the employee Cesu number it was read for (no NIR or administrative data, ADR 0007). Employers and
  mandates carry the host's `ExternalReference`, unique per tenant.
- `Domain\Mandate\Mandate` (aggregate, per tenant and employer): `notified` → `confirmed` (020) → `cancelled`;
  re-notification after a cancellation creates a new mandate.
- `Domain\Declaration\DeclarationRecord` (aggregate): `pending` → `submitting` (atomic claim) → `declared`
  (with `DeclarationResult`) | `rejected` (final, errors kept) | `already_declared` (final) | `outcome_unknown`
  (ADR 0003) | `error` (retryable failure proven not sent, back to `pending` until `max_submission_attempts`) |
  `invalid_credentials` (requeued by `RequeueAfterInvalidCredentials`). Invalid transitions throw
  `InvalidStateTransition`.
- `DeclareSalary` creates the record, claims it and submits it synchronously. The record never holds the payload
  (ADR 0007): a retry is the host calling `DeclareSalary` again with the same external reference and declaration
  (checked by a fingerprint that excludes personal data); there is no asynchronous declaration submission.
  Idempotency: same tenant + external reference + same content returns the existing record; a different content
  throws `DeclarationAlreadyRecorded`.
- Amendment (2026-11-15, lead-accepted): a `rejected` record (URSSAF answered 400 and recorded nothing) is superseded by
  the next `DeclareSalary` with the same external reference, whatever its content: the rejected record is deleted and a
  new one (new id) is recorded in the same transaction. Every other state follows the re-entry table of
  docs/workflow.md (same content: replayed or resumed; different content: `DeclarationAlreadyRecorded`). An `error`
  record that exhausted `max_submission_attempts` has no domain exit: `DeclareSalary` reports it as `exhausted` without
  calling URSSAF until the policy maximum is raised.
- `SyncEmployerStatus` (020) refreshes employer and mandate state; it is the only polling.

## Consequences

- Unique constraints: employers `(tenant, numero_cesu)`, mandates one active per `(tenant, employer)`, declarations
  `(tenant, external_reference)`.
