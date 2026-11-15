# ADR 0005 — Classification of ERR_METIER errors

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

`ERR_METIER` is a generic code; its `description` text is the only discriminator, the texts carry typos, leading
spaces, doubled apostrophes and placeholders (`XX,XX`, `XXX`, `X SMIC`) (api-reference §1.3, §5.20).

## Decision

- `Wire\ErrorClassifier` normalises a description (trim, collapse whitespace, `’` → `'`, `''` → `'`, lower case,
  placeholders as wildcards) and matches it against a table of known texts per operation, producing
  `Application\Port\Urssaf\BusinessError` (string enum, e.g. `duplicate_declaration`, `iban_invalid`,
  `email_already_known`, `cesu_plus_deadline_exceeded`) or `null` when unknown.
- Every other code maps to `ApiErrorCode` (§3.13) and `ErrorKind` (`invalid_parameter`, `business`, `mandate_missing`,
  `rib_missing`, `sepa_missing`, `mandate_held_elsewhere`, `account_unknown`, `aici_not_active`, `nature_*`,
  `deposit_too_high`, `technical`, `unknown`).
- The raw `code`, `message` and `description` are always kept (`ApiError`); unknown codes are not errors of the
  package.

## Consequences

- A text change on the URSSAF side degrades the classification to `null`, never to a wrong category.
- The table is data in one class, covered by one test per entry built from the PDF texts.
