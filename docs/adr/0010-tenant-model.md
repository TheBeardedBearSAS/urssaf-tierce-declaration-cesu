# ADR 0010 — Tenant model

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The reference is multi-tenant: `TenantId` on every aggregate, `CredentialsProvider::forTenant()`.

## Decision

- A tenant is one set of OAuth2 credentials of a third-party declarant platform. Employers, mandates and
  declarations belong to a tenant; the same `numeroCesu` may exist under two tenants (the API forbids two platforms at
  the same time, the package does not enforce it across tenants).
- Token cache key: hash of environment, base URI and client id (reference behaviour).

## Consequences

- Commands take `--tenant`; the bundle declares tenants under `credentials`.
