# ADR 0012 — Public integration events

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

With full parity, the package exposes versioned integration events through an outbox (reference `Contract\Event`).

## Decision

- `Contract\Event\IntegrationEvent` (abstract readonly, scalar properties `eventId`, `occurredAt`, `tenantId`,
  `aggregateId`, `externalReference`) and the v1 catalogue:
  `urssaf.cesu.employer.registered`, `urssaf.cesu.employer.status_changed`, `urssaf.cesu.mandate.notified`,
  `urssaf.cesu.mandate.cancelled`, `urssaf.cesu.declaration.declared`, `urssaf.cesu.declaration.rejected`,
  `urssaf.cesu.declaration.outcome_unknown`.
- Amendment (2026-11-15, lead-accepted): the v1 catalogue also contains `urssaf.cesu.mandate.confirmed` (the host learns
  that URSSAF reflects the mandate, the prerequisite of 050) and `urssaf.cesu.declaration.already_declared` (a final
  outcome the host must act on: neither declared nor rejected), both VERSION 1. `urssaf.cesu.employer.status_changed` is
  raised by a 020 refresh and by a local stage change (030 / 031, snapshot fields null); the Cesu+ state and its blocking
  reason are pair-relative free text and are not part of it. No integration event exists for requeued declarations,
  failed submissions or rejected credentials (their domain events carry no external reference): hosts list them with
  `FindDeclarations::inState(pending)`.
- Amendment (2026-11-16, lead-accepted, lot 10): the v1 catalogue also contains `urssaf.cesu.employer.forgotten`
  (VERSION 1, [ADR 0015](0015-employer-erasure.md)): an employer was erased from the package store; it carries the
  Cesu number and the lists of erased mandate ids and declaration record ids (identifiers only). The outbox entries of
  the erased aggregates are not purged.
- Payloads carry identifiers (`numeroCesu`, `idDeclaration`), states and codes, never names, birth dates, NIR or
  bank data.

## Consequences

- Documented in `docs/integration-events.md`; any change of payload bumps `VERSION`.
