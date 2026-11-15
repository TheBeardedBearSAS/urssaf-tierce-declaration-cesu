# ADR 0008 — Encoding of the `accord` query parameter (060)

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The Swagger types `accord` as boolean; the PDF documents `0` / `1` and its example uses `accord=1` (§5.6).

## Decision

- `ApiConfiguration::agreementEncoding()` (`AgreementEncoding::Boolean` → `true`/`false`, default;
  `AgreementEncoding::Numeric` → `1`/`0`); bundle key `agreement_encoding: boolean|numeric`.

## Consequences

- The contract tests settle it; the default follows the Swagger, the source that types the field.
