# ADR 0001 — Date-only encoding and business time zone

Status: Accepted (2026-11-15). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

Every date of the CESU contract is `format: date` (`YYYY-MM-DD`): birth dates, employment period, payment date,
paid-leave periods, approval dates (api-reference §2, §4.1). The reference package sends calendar dates as
`YYYY-MM-DDT00:00:00Z` and employment bounds as UTC instants, because the Tiers de prestations API compares them with
an instant. Rules that compare with "today" (employment start not in the future, Cesu+ deadline on the 15th of the
following month) need a reference time zone that URSSAF does not publish (§5).

## Decision

- `Domain\Shared\CalendarDate` is the only date type used on the wire, encoded and decoded as strict `Y-m-d`
  (`Wire\DateEncoding`); responses with a time part are refused (`UnexpectedUrssafResponse`) since the contract has none.
- "Today" is computed from the `Clock` port in a configurable business time zone, default `Europe/Paris`
  (`WorkflowPolicy::businessTimeZone()`, `ApiConfiguration` for the gateway, `business_timezone` in the bundle).
- `Domain\Shared\Instant` (UTC, second precision) is kept for local tracking only (created/updated/claimed at,
  events, outbox).

## Consequences

- No `T00:00:00Z` on the wire; the reference `DateEncoding` is not reused as is.
- The time zone stays an open point (§5): changing it is a configuration change, not a code change.
