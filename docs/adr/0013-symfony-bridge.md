# ADR 0013 — Symfony bridge

Status: Accepted (2026-11-15, lead-accepted: every recommendation of the lot 6 design is binding). Relates to
[ADR 0000](0000-reference-architecture.md) (layers, bridges), [ADR 0003](0003-retry-and-unknown-outcome.md),
[ADR 0006](0006-local-lifecycles.md), [ADR 0007](0007-personal-data.md), [ADR 0010](0010-tenant-model.md) and
[ADR 0012](0012-integration-events.md).

## Context

The package ships a Symfony 6.4 / 7.x bundle (`Bridge\Symfony`), as the reference package does. The CESU workflow has
no payment request, no pairing and no status polling, so part of the reference bridge has no equivalent, and a few
reference choices do not fit this package (shared names with a second URSSAF package, a Doctrine bridge that comes
later, personal values on the command line).

## Decision

1. **Names.** Alias and configuration root `urssaf_tierce_declaration_cesu`, service ids, parameter, tag and
   translation domain prefixed `urssaf_tierce_declaration_cesu`, console namespace `urssaf:cesu:`, lock prefix
   `urssaf_cesu`, Monolog channel `urssaf_cesu`, schedule `urssaf_cesu`, recipe variables `URSSAF_CESU_ENV`,
   `URSSAF_CESU_CLIENT_ID` and `URSSAF_CESU_CLIENT_SECRET`. The two URSSAF packages can be installed in the same
   application: nothing is shared.
2. **Messenger scope.** Two messages only, both safe to retry and carrying identifiers only: refresh the employers of a
   tenant, refresh one employer (method 020). No declaration message (`DeclareSalary` is synchronous and the record never
   holds the payload, ADR 0006), no 010 / 030 / 031 / 060 / 070 message (an answer is needed, 010 / 060 / 070 are never
   resent, ADR 0003), no auto-submit listener and no `auto_submit` option.
3. **Persistence drivers.** `in_memory` (default), `custom` and `doctrine` are in the enum so that the configuration
   tree is stable, but `doctrine` is refused at build time until the Doctrine bridge exists. The reference default
   (`doctrine` when the ORM is installed) is not reproduced.
4. **Transaction manager.** The `custom` driver accepts `persistence.transaction_manager` (default
   `ImmediateTransactionManager`): the outbox atomicity of ADR 0012 needs the transaction manager that the custom
   repositories share.
5. **One business time zone.** `business_timezone` feeds both `ApiConfiguration` and `WorkflowPolicy`.
6. **Hidden prompts.** The employer birth date and the employee NIR are read from a hidden prompt, never from the
   arguments (shell history, process list, cron logs), never echoed; non-interactive mode exits with code 2 (ADR 0007).
7. **No URSSAF description printed.** Commands print codes and package messages only: a description may echo personal
   values and console output ends in cron logs.
8. **Exit codes.** `2` (`Command::INVALID`) for invalid usage, `1` for a failure, `0` otherwise.
9. **Simulator guard.** `gateway.simulated` is refused with the literal `production` at build time, and the simulator
   factory refuses it at runtime when the environment is a placeholder resolved to `production`.
10. **Diagnostics.** `urssaf:cesu:check` has a `configuration` check (a runtime-resolved environment, base URI or header
    fails as a result, not as a stack trace) and a `persistence` check (warning for `in_memory`); the checks that need
    the configuration receive closures, so that a broken configuration cannot prevent the command from running.
11. **`urssaf:cesu:employer:import`.** Tracks an employer that is already registered at URSSAF (no URSSAF call): the
    mandate commands need a tracked account.
12. **No `ext-filter` / `ext-ctype`.** Integer options are parsed with `preg_match`.
13. **Defaults.** Safety cron frequencies: employers hourly, stale declarations every 15 minutes, outbox relay every
    minute, outbox purge daily; `rate_limiter.window` in integer seconds; lock scope of a single-employer refresh is the
    raw selector value in the command and the account id in the message (a read, idempotent, documented).
14. **Deferred.** The GDPR erasure command needs an Application use case that does not exist; it is deferred to a lot
    after the Doctrine bridge.

## Consequences

- The Doctrine lot deletes the `doctrine` refusal rule, adds the default driver, the entity manager option, the
  Doctrine mapping prepend and the schema check, and switches the default.
- Documented in `docs/symfony.md` and `docs/testing.md` (Symfony section).
