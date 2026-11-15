# ADR 0011 — Quotas and rate limiting

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

PDF §2.5 publishes a default quota of 2 500 requests per hour in production, global and per client; no
per-operation or sandbox values (api-reference §1.1). The reference limits per operation per minute.

## Decision

- `SlidingWindowRateLimiter` counts calls per tenant over a configurable window, default 2 500 per 3 600 s, all
  operations together; token calls are not counted. Disabled by default (`NoopRateLimiter`), as in the reference.
- 429 is a `UrssafUnavailable` with `isQuotaExceeded()`; `Retry-After` honoured when present.

## Consequences

- No per-operation limit is invented; the limiter is per process (documented).
