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
  `cesu_plus_active` → `aici_active`; `mandate_cancelled` after 031. Stages follow 020, they never block a call.
- `Domain\Mandate\Mandate` (aggregate, per tenant and employer): `notified` → `confirmed` (020) → `cancelled`;
  re-notification after a cancellation creates a new mandate.
- `Domain\Declaration\DeclarationRecord` (aggregate): `pending` → `submitting` (atomic claim) → `declared`
  (with `DeclarationResult`) | `rejected` (final, errors kept) | `already_declared` (final) | `outcome_unknown`
  (ADR 0003) | `error` (retryable failure proven not sent, back to `pending` until `max_submission_attempts`) |
  `invalid_credentials` (requeued by `RequeueAfterInvalidCredentials`). Invalid transitions throw
  `InvalidStateTransition`.
- `DeclareSalary` creates the record, claims it and submits it synchronously; Messenger can run the submission
  asynchronously from the same record. Idempotency: same tenant + external reference + same content returns the
  existing record; a different content throws `DeclarationAlreadyRecorded`.
- `SyncEmployerStatus` (020) refreshes employer and mandate state; it is the only polling.

## Consequences

- Unique constraints: employers `(tenant, numero_cesu)`, mandates one active per `(tenant, employer)`, declarations
  `(tenant, external_reference)`.
