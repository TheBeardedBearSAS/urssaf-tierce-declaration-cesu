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
- Payloads carry identifiers (`numeroCesu`, `idDeclaration`), states and codes, never names, birth dates, NIR or
  bank data.

## Consequences

- Documented in `docs/integration-events.md`; any change of payload bumps `VERSION`.
