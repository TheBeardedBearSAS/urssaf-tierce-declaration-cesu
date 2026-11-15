# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Project skeleton: Docker tooling, quality gates (`make ci`) and GitHub Actions CI, identical to the other URSSAF
  packages.
- Specification: official OpenAPI contracts (`docs/openapi/`), official documentation (links in `docs/api-reference.md`, local copy in the git-ignored `docs/reference/`), technical
  reference (`docs/api-reference.md`) and implementation roadmap (`docs/roadmap.md`).
- Signed releases (workflow `release.yml`): each GitHub release carries the distribution archive, its `SHA256SUMS`
  and a SLSA build provenance attestation (`*.intoto.jsonl`, Sigstore); see *Verifying a release* in SECURITY.md.
- CodeQL analysis of the GitHub Actions workflows (workflow `codeql.yml`) on every pull request and push to `main`.
- Architecture decision records (`docs/adr/`): the architecture inherited from `thebeardedbear/urssaf-tiers-prestations`
  (ADR 0000) and the CESU-specific decisions (ADR 0001 to 0014).
- Domain layer (`src/Domain`): shared value objects (tenant, external reference, calendar date, instant, money in cents,
  half-days, e-mail, phone, country code, Cesu number), exception hierarchy and validation notification pattern;
  employer registration input, address and bank details, 020 status snapshot; employee identification sum type (Cesu
  number, NIR, administrative data); declaration sum type (standard, Cesu+), incomes, supplements, paid leave, foster
  carer approval, result; contribution estimate; local lifecycles of employers, mandates and declarations (ADR 0006);
  drafts and factories reporting every violation; local pre-checks of ADR 0009.
- Application ports (src/Application/Port): credentials, token store, clock, id generator, rate limiter (global hourly
  quota, ADR 0011), the nine operations with their retry safety (ADR 0003), event publisher, outbox and integration event
  sink, transactions, workflow statistics; the CesuGateway port split per operation (010 to 070), its query and result
  DTOs, raw API errors, their classification (ErrorKind, BusinessError, ADR 0005) and the gateway exceptions including
  OutcomeUnknown; the 070 AICI activation request (Domain). Contract layer with the IntegrationEvent base class
  (ADR 0012). Wire layer (src/Infrastructure/Urssaf/Wire): defensive JSON payload reader, float-free JSON encoder,
  date-only dates (ADR 0001), amounts in cents, half-days, query parameters and accord encoding (ADR 0008), request and
  response mappers of every operation, error payload parser, ERR_METIER classifier and error code catalogue.
- Infrastructure HTTP gateway (src/Infrastructure/Urssaf): UrssafHttpGateway implementing every CESU operation (010 to
  070) over PSR-18 / PSR-17, built with UrssafGatewayFactory (HttpTransport, ApiConfiguration, GatewayOptions); OAuth2
  client credentials with a shared token cache, one replay after a 401, Retry-After, status mapping of ADR 0002, unknown
  outcomes for 010/050/060/070 (ADR 0003), optional request-id / application-id headers, configurable accord encoding
  (ADR 0008) and business time zone. Adapters: in-memory, PSR-6 and PSR-16 token stores, PSR-20 and frozen clocks, UUID
  v4 generator, no-op and sliding-window (2 500 calls/hour per tenant, ADR 0011) rate limiters, in-memory credentials
  provider, PSR-3 failure reporter. Runtime dependencies psr/cache, psr/clock, psr/http-client, psr/http-factory,
  psr/http-message, psr/log, psr/simple-cache.
- Application layer (src/Application): use cases RegisterEmployer (010, synchronous, bank details never stored), ImportEmployer, RegisterMandate (030), CancelMandate (031), SyncEmployerStatus / SyncEmployerStatuses / SyncAllEmployerStatuses (020), ActivateCesuPlus (060), ActivateAici (070), DeclareSalary (050: idempotent per tenant and external reference with a content fingerprint, mandate guard, atomic claim, unknown outcomes never resent), ResolveDeclarationOutcome, ReconcileStaleDeclarations, RequeueAfterInvalidCredentials, RelayOutbox, PurgeOutbox; queries VerifyEmployer, VerifyEmployee, EstimateContributions, GetEmployerAccount, GetDeclaration, FindDeclarations, GetWorkflowOverview; WorkflowPolicy; UnitOfWork with domain events published after commit and integration events written to an outbox. Public integration events urssaf.cesu.* (Contract\Event, ADR 0012) and IntegrationEventCodec. In-memory repositories, workflow statistics and outbox, PSR-14 event publisher and integration event sink, immediate transaction manager. Runtime dependency psr/event-dispatcher.
- Documentation: docs/workflow.md (lifecycles, idempotency, retries, monitoring) and docs/integration-events.md (event catalogue, outbox, relay); ADR 0006, 0009, 0012 and 0000 amendments (2026-11-15).
- Framework-agnostic wiring (src/Standalone): UrssafTierceDeclarationCesuBuilder (immutable; token store, clock, id generator, event publisher, logger, rate limiter, workflow policy, repositories, outbox and sink, gateway override) and UrssafTierceDeclarationCesu (one accessor per use case). Testing simulator (src/Testing): SimulatedCesuGateway, an in-memory CESU platform for host test suites (employer registry, exclusive mandates, Cesu+ and AICI activation, estimates, declarations with the duplicate rule and MANDAT_INEXISTANT, scripted failures, refusals and lost answers, call inspection). Deptrac layers Testing and Standalone.
- Symfony bundle (UrssafTierceDeclarationCesuBundle, alias urssaf_tierce_declaration_cesu, Symfony 6.4 and 7.x): configuration tree validated at build time, HTTP gateway with a dedicated HttpClient and a PSR-6 token pool, optional rate limiter, in-memory or custom persistence, outbox with Messenger / event dispatcher / custom sinks, console commands urssaf:cesu:* (verify-employer, verify-employee, estimate, employer:import, mandate:register, mandate:cancel, employers:sync-status, declarations:reconcile-stale, declarations:resolve, requeue-invalid-credentials, outbox:relay, outbox:purge, status, check), Messenger messages for the employer status refresh, non-blocking locks, Symfony Scheduler schedule, installation diagnostics, a ViolationListConverter and English / French translations of every violation code; Symfony Flex recipe and bin/build-flex-endpoint (ADR 0013, docs/symfony.md).
- Doctrine bridge (Doctrine ORM 3 / DBAL 4): XML mapping of the employer accounts, mandates and declaration records (tables urssaf_cesu_*), custom DBAL types, repositories with atomic claims, uniqueness reported with the in-memory codes and optimistic concurrency checks, outbox, workflow statistics and transaction manager; Standalone withDoctrine(); Symfony "doctrine" persistence driver (default when doctrine/orm is installed) with a configurable entity manager and a database schema check. No IBAN, BIC nor NIR column (ADR 0007); encryption recipe for the employer birth date in docs/doctrine.md (ADR 0014).

### Changed

- CI: verifiable quality proofs (Codecov, Stryker Dashboard, SonarQube Cloud, OpenSSF Scorecard; active once the
  repository is public), every GitHub Action pinned to a commit SHA and kept up to date by Dependabot, hadolint on the
  Dockerfile (`make hadolint`, part of `make ci`) with a non-root development image, and benchmarks with PHPBench
  (`make bench`, CI job `benchmark`, isolated in `tools/phpbench`).
- CI: the SonarQube Cloud analysis also requires the repository variable `SONARCLOUD=enabled`, set once the
  project is imported on SonarQube Cloud.

- README: maintainer (The Bearded Bear), the other URSSAF packages and professional support; composer
  `authors` now points to https://the-bearded-bear.com.

- The package is open source under the MIT license (previously proprietary). Added `CONTRIBUTING.md` and
  `SECURITY.md`, and a notice in the README stating that the package is unofficial and not affiliated with URSSAF.
- README: architecture overview and the sandbox contract test variables referenced by `CONTRIBUTING.md`.
