# ADR 0007 — Storage of personal and banking data

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The package handles NIR, names, birth dates, e-mail, phone, address, IBAN and BIC (api-reference §4.12). The
roadmap requires tracking "with no personal data beyond what the use case needs; NIR, IBAN and BIC encrypted or not
stored". The reference documents encryption at rest as a recipe (DBAL type override).

## Decision

- IBAN and BIC are never persisted: `RegisterEmployer` (010) runs synchronously and only the returned `numeroCesu`
  and registration date are stored. No pending queue for 010.
- The NIR is never persisted: a declaration record keeps the employee reference kind and, when known, the employee
  Cesu number; administrative data (names, birth data, address, e-mail, phone) of the employee are not stored.
- The employer `numeroCesu` and birth date are stored: every later call (020, 030, 031, 050, 060) needs them.
- Values are masked in `__debugInfo`, never logged and never put in exception messages; `#[SensitiveParameter]` on
  every parameter carrying them.
- `docs/doctrine.md` documents encrypting the birth date column with a DBAL type override, as in the reference.

## Consequences

- A declaration cannot be re-sent from storage alone (consistent with ADR 0003).
- Hosts keep their own copy of payroll data if they need it.

## Amendment (2026-11-15, lead-accepted, lot 7)

- The Doctrine bridge ([ADR 0014](0014-doctrine-bridge.md)) has no column for IBAN, BIC, NIR, employee names, birth
  data, address, e-mail or phone (asserted by a test).
- The amounts of the declaration result (gross and net taxable salary of the employee, contributions, debits) are kept
  in the declaration record, as are the URSSAF error descriptions (they may echo host values). They are personal data:
  retention is the host's policy, and the encryption recipe in `docs/doctrine.md` covers them.
- The employer birth date has its own DBAL type (`urssaf_cesu_birth_date`) so that it can be encrypted alone.
- Conversion errors on these columns never contain the stored value.

## Amendment (2026-11-16, lead-accepted, lot 10)

- The package offers an erasure use case, `ForgetEmployer`, and a console command, `urssaf:cesu:employer:forget`
  ([ADR 0015](0015-employer-erasure.md)). It erases the local copy only; retention remains the host's policy and
  nothing is erased at URSSAF.
