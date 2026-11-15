# ADR 0004 — Sum types for declarations and employee references

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The roadmap models `Declaration` as `StandardDeclaration|CesuPlusDeclaration` and `EmployeeReference` as Cesu number
| NIR | administrative data. PHPStan `ergebnis.noExtends` forbids class inheritance outside the justified extension
points; the reference uses interfaces with final implementations for closed alternatives (`DraftReading`).

## Decision

- `Domain\Declaration\Declaration` is an interface implemented by final readonly `StandardDeclaration`
  (`typeDeclaration = 0`) and `CesuPlusDeclaration` (`typeDeclaration = 1`, nature required). Shared fields live in a
  final readonly `DeclarationContent` composed by both.
- `Domain\Employee\EmployeeReference` is an interface implemented by `EmployeeByCesuNumber`, `EmployeeByNir` and
  `EmployeeByIdentity`; the declaration variant `DeclaredEmployee` composes one of them with the declaration-only
  fields (`typeEmploi`, `congesPayes`, address, civility...).
- Consumers branch with `match (true) { $x instanceof A => ..., }` and a final `default` throwing `InvariantViolation`
  (kept for exhaustiveness, covered by a test double in tests only).

## Consequences

- No `extends` in the domain; adding a variant means updating every `match` (caught by tests and Infection).
