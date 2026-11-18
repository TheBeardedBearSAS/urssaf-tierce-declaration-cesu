# ADR 0000 — Reference architecture inherited from urssaf-tiers-prestations

Status: Accepted. Reference: thebeardedbear/urssaf-tiers-prestations (HEAD a9f9023), README "Architecture",
docs/api-reference.md §4, docs/workflow.md, docs/symfony.md, docs/doctrine.md, docs/testing.md, phpstan.neon.dist,
mago.toml, rector.php.

## Context
Every URSSAF package shares one architecture so integrators and maintainers move between them freely. This ADR lists
what this package inherits, adapts or leaves out. Any further deviation needs its own ADR (Context / Decision /
Consequences).

## Inherited as is
1. Layers (Deptrac): Domain (no dependency), Contract (no dependency), Application → Domain, Contract;
   Infrastructure → Application, Domain, Contract, Psr; Testing → Domain, Contract, Application, Infrastructure;
   Standalone → … + DoctrineBridge, Doctrine, Psr; DoctrineBridge → … + Doctrine, Psr; SymfonyBridge → everything
   + Testing, Symfony, Doctrine, Psr. Tests are not analysed.
2. PSR-only runtime: php >= 8.2, ext-*, psr/* interfaces. Symfony, Doctrine and HTTP clients are require-dev +
   suggest, with the reference conflict floors.
3. Value objects: final readonly, private constructor, named constructors (of/fromString/fromCents), stable
   violation codes `<subject>.<reason>`, no raw value in messages; personal data masked (__debugInfo,
   #[SensitiveParameter]); Credentials not serializable.
4. Validation: notification pattern (Violation, ValidationResult, Validator), factories that collect every
   violation of a draft at once (ValidationFailed).
5. Errors: UrssafException marker, RetryableException marker, abstract DomainException (violationCode, params,
   toViolation), InvalidValue / InvariantViolation / InvalidStateTransition / RecordNotFound; gateway channel split:
   UrssafRequestRejected (400), UrssafAuthenticationFailed (401/403/512, token 400/401/403), UrssafUnavailable
   (429/5xx/transport, mayHaveReachedUrssaf), UnexpectedUrssafResponse. Wire codes are classified in
   Infrastructure (ErrorClassifier) and exposed as port enums; the Application never compares wire strings.
6. Wire: Payload as the single reader of untyped data, JsonEncoder refusing floats (JsonDecimal literals), money in
   integer cents, unknown codes kept raw next to a nullable enum.
7. HTTP: OAuth2 client credentials with a TokenStore cache (key = hash of environment, base URI, client id),
   one replay after 401, RetryAfter (seconds only), sandbox host overridable, nothing secret or personal logged.
8. Application: one final readonly handler per use case with __invoke; ports implemented by outer layers;
   UnitOfWork publishing domain events after commit, failures isolated (FailureReporter); atomic claim before any
   URSSAF write; attempt counter and invalid_credentials state with requeue; WorkflowPolicy for every tunable.
9. Events: domain events recorded by aggregates; public integration events (Contract layer, scalar, versioned
   TYPE/VERSION) written to an outbox in the same transaction and relayed at least once.
10. Multi-tenant: TenantId on every aggregate and row; CredentialsProvider::forTenant(); unique constraints, locks
    and rate limiting per tenant. Here a tenant is one set of platform credentials (one third-party declarant).
11. Bridges: AbstractBundle, ServiceIds constants, BundleConfiguration with build-time rules, console commands,
    Messenger messages (scalar) with recoverable/unrecoverable mapping, non-blocking Lock, Scheduler, diagnostics,
    translations en/fr; Doctrine XML mapping, custom DBAL types, dedicated entity manager recommended, no migrations
    shipped, Flex recipe.
12. Tests: Unit / Functional / Contract suites, hand-written fakes over mocks, shared repository contract traits run
    against in-memory and Doctrine, Contract suite skipped without URSSAF_SANDBOX_CLIENT_ID/SECRET and run only by a
    manual CI job; 100 % lines/methods/classes, Infection MSI 100 %, phpstan max, mago, rector, cs, deptrac,
    composer checks, guard.
13. Quality exceptions: only those justified in the reference configs (container parameters, noExtends for framework
    extension points / IntegrationEvent / DomainException, default and nullable parameters for value objects,
    nullable returns for finders, non-final Doctrine entities, static assertions in tests, wire DTO constructor
    threshold 12, no mago fmt).

## Adapted (each with its own ADR)
- Dates are date-only YYYY-MM-DD (no T00:00:00Z, no instants on the wire) — ADR 0001.
- HTTP client: no request headers by default (the contract declares none), empty 200 bodies (030/031), DELETE and
  query-only POST, a global hourly quota (2 500/h, PDF §2.5) instead of per-operation quotas — ADR 0002.
- Retries: no reconciliation lookup exists (no GET for declarations, no search for employers); 010 and 050 are never
  resent, an uncertain outcome becomes `outcome_unknown` / manual review — ADR 0003.
- Sum types (Declaration, EmployeeReference): interface + final implementations — ADR 0004.
- ERR_METIER is classified by its description text — ADR 0005.
- Local lifecycles (employer, mandate, declaration) replace pairing / payment-request status polling — ADR 0006.
- Personal and banking data storage — ADR 0007.
- `accord` encoding configurable — ADR 0008.
- Mandate guard and AF fail-fast opt-outs — ADR 0009.
- `ApiConfiguration` keeps the reference public constructor with defaulted parameters plus `forEnvironment()`, and
  carries more settings (optional request-id and application-id headers, `accord` encoding, business time zone
  instead of the pairing time zone); header names are validated by `HttpHeader`. (lead-accepted 2026-11-15)
- Test repositories (`Journaling*`) are decorators over the production in-memory repositories, instead of the reference's
  separate fake repositories: no duplicated query logic, the shared contract traits prove the inner ones.
  (lead-accepted 2026-11-15)
- Wire mappers are split per direction (`…RequestMapper` / `…ResponseMapper`) instead of one class per operation (lot 2).
- Symfony bridge: names, Messenger scope without auto-submit, `doctrine` default driver (since ADR 0014), hidden
  prompts, exit code 2, extra diagnostics — ADR 0013.
- Doctrine bridge: no progress table, active-mandate key table, native version field, JSON columns, dedicated birth
  date type, default driver with fail-fast — ADR 0014.

- PHPStan analyses against PHP 8.2 (`phpVersion: 80200`) and `type_coverage.constant` is disabled: typed class
  constants need PHP 8.3, so the reference setting (inert on PHP 8.2) failed `make ci PHP_VERSION=8.4`
  (lead-accepted 2026-11-15).

## Not applicable
- Batching by 10 (050/070 of Tiers de prestations): every CESU call is unitary.
- ASCII transliteration of names: the CESU contract accepts the accented PDF character set (api-reference §2.1).
- URSSAF status polling with polling windows (settled/unknown/max age): CESU has no status endpoint; only the employer
  status (020) is re-read.
- Pairing acceptance instant as lower bound of employment periods: no equivalent.
- billing_third_party_id and x-request-id / recouv.application.id headers: not in the CESU contract.
- Tenant model (one tenant = one platform credential set) — ADR 0010.
- Quotas: one global hourly window, no per-operation limit — ADR 0011.
- Integration event catalogue (`urssaf.cesu.*`) — ADR 0012.

## Process
New decisions and deviations from the reference get an ADR `docs/adr/NNNN-<title>.md` (Context / Decision /
Consequences), accepted by the lead before the code that implements them.

## Amendment (2026-11-18, lead-accepted)

- Infection `timeout` is 180 s instead of the reference 30 s, and `make infection` and the CI job fail when a mutant
  is *skipped*: Infection never runs a mutant whose covering tests exceed the limit in total, while MSI still reads
  100 %. The change makes the gate stricter (justified in `infection.json5`).
  The gate also fails on *timed out* and *syntax error* mutants (`timeoutsAsEscaped`, plus summary checks): neither is
  a verdict, so none may go unevaluated; each mutant runs only its covering test cases (`--only-covering-test-cases`),
  so it never waits on unrelated kernel-booting tests. Comparisons whose mutants would not parse use a named variable.
