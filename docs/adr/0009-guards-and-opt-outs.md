# ADR 0009 — Local guards and their opt-outs

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

api-reference §4.2, §4.10 and §4.11 ask for a mandate guard on 050/060, an AF fail-fast and cheap local pre-checks,
without inventing rules absent from the specification.

## Decision

- `Application\Service\MandateGuard` refuses 050 and 060 (`MandateRequired`) when no non-cancelled local mandate exists
  for the employer, unless the command sets `skipMandateCheck: true` (policy default `requireLocalMandate: true`).
- `typeEmploi = AF` declarations are refused locally (`declaration.foster_carer_unsupported`) unless
  `WorkflowPolicy::allowFosterCarerDeclarations()` is enabled.
- Local validation covers the contract bounds (lengths, ranges, multiples, enums, patterns), the address rules of PDF
  §3.1.4, the employee identification modes and these cheap checks: `dateDebut <= dateFin`, same month, start not
  after today, overtime +25 % only with at least 40 normal hours, +50 % only with at least 8 hours at +25 %,
  `heuresCp`/`periodesCongesPayes` forbidden when `congesPayes = true`, leave periods ordered, non-overlapping and
  inside the employment period, Cesu+ requires a nature, AF forbids one.
- SMIC, ceilings (6 000 €, 99 999,99 €), deposit, deadline and eligibility rules stay server-side.

## Consequences

- A local refusal never reaches URSSAF; the opt-outs exist for hosts that track mandates elsewhere.
