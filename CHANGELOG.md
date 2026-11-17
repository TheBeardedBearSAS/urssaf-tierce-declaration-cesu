# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Every source file now carries a copyright and `SPDX-License-Identifier: MIT` header, enforced by PHP CS Fixer
  (`header_comment`) for PHP and by `make guard` (`bin/check-spdx`) for the other source files.

### Fixed

- Contract suite: `URSSAF_SANDBOX_TOKEN_URI` (and `URSSAF_SANDBOX_BASE_URI`) must now be an https URI and can no longer target
  the production host (`api.urssaf.fr`, case and trailing dot ignored). The sandbox client credentials are posted to the
  token URI, so a production value would have leaked them. Test tooling only, the published SDK is unchanged.

## [0.1.0] - 2026-11-16

First release.

### Upgrading

- Driver `doctrine`: generate and run the migration for the `urssaf_cesu_*` tables (`doctrine:migrations:diff`,
  `doctrine:migrations:migrate`); the package ships no migration. `urssaf:cesu:check` reports the missing tables.

### Added

- Project skeleton: Docker tooling, quality gates (`make ci`) and GitHub Actions CI, identical to the other URSSAF
  packages.
- Signed releases (workflow `release.yml`): each GitHub release carries the distribution archive, its `SHA256SUMS`
  and a SLSA build provenance attestation (`*.intoto.jsonl`, Sigstore); see *Verifying a release* in SECURITY.md.
- CodeQL analysis of the GitHub Actions workflows (workflow `codeql.yml`) on every pull request and push to `main`.
- Specification and documentation: official OpenAPI contracts (`docs/openapi/`), technical reference
  (`docs/api-reference.md`, links to the official documentation, local copy in the git-ignored `docs/reference/`),
  roadmap, architecture decision records (`docs/adr/`: the inherited architecture, ADR 0000, and the CESU-specific
  decisions, ADR 0001 to 0015), `docs/workflow.md`, `docs/integration-events.md`, `docs/standalone.md`,
  `docs/symfony.md`, `docs/doctrine.md` and `docs/testing.md`.
- Domain layer (`src/Domain`): shared value objects (tenant, external reference, calendar date, instant, money in cents,
  half-days, e-mail, phone, country code, Cesu number), exception hierarchy and validation notification pattern;
  employer registration input, address and bank details, 020 status snapshot; employee identification sum type;
  declaration sum type (standard, Cesu+), incomes, supplements, paid leave, foster carer approval, result; contribution
  estimate; local lifecycles of employers, mandates and declarations (ADR 0006); drafts and factories reporting every
  violation; local pre-checks (ADR 0009).
- Application ports and wire layer: ports for credentials, token store, clock, id generator, rate limiter, event
  publisher, outbox and integration event sink, transactions and workflow statistics; the `CesuGateway` port split per
  operation (010 to 070) with its DTOs, raw API errors, their classification (`ErrorKind`, `BusinessError`, ADR 0005)
  and the gateway exceptions including `OutcomeUnknown` (ADR 0003); public integration events (`Contract\Event`,
  ADR 0012); wire layer (`Infrastructure\Urssaf\Wire`) with float-free JSON encoding, date-only dates (ADR 0001),
  amounts in cents, `accord` encoding (ADR 0008), request and response mappers of every operation, error payload parser
  and `ERR_METIER` classifier.
- HTTP gateway (`Infrastructure\Urssaf`): `UrssafHttpGateway` implementing every CESU operation over PSR-18 / PSR-17,
  built with `UrssafGatewayFactory`; OAuth2 client credentials with a shared token cache, one replay after a 401,
  `Retry-After`, status mapping (ADR 0002), unknown outcomes for 010 / 050 / 060 / 070 (ADR 0003), optional request-id
  and application-id headers, business time zone. Adapters: in-memory, PSR-6 and PSR-16 token stores, PSR-20 and frozen
  clocks, UUID v4 generator, no-op and sliding-window rate limiters (2 500 calls per hour per tenant, ADR 0011),
  in-memory credentials provider, PSR-3 failure reporter. Runtime dependencies on `psr/cache`, `psr/clock`,
  `psr/event-dispatcher`, `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/log` and `psr/simple-cache`.
- Application layer (`src/Application`): `RegisterEmployer` (010, synchronous, bank details never stored),
  `ImportEmployer`, `RegisterMandate` (030), `CancelMandate` (031), `SyncEmployerStatus` / `SyncEmployerStatuses` /
  `SyncAllEmployerStatuses` (020), `ActivateCesuPlus` (060), `ActivateAici` (070), `DeclareSalary` (050: idempotent per
  tenant and external reference, mandate guard, atomic claim, unknown outcomes never resent),
  `ResolveDeclarationOutcome`, `ReconcileStaleDeclarations`, `RequeueAfterInvalidCredentials`, `RelayOutbox`,
  `PurgeOutbox`; queries `VerifyEmployer`, `VerifyEmployee`, `EstimateContributions`, `GetEmployerAccount`,
  `GetDeclaration`, `FindDeclarations`, `GetWorkflowOverview`; `WorkflowPolicy`; `UnitOfWork` publishing domain events
  after commit and writing integration events to an outbox; in-memory repositories, statistics and outbox.
- Framework-agnostic wiring (`src/Standalone`): `UrssafTierceDeclarationCesuBuilder` and `UrssafTierceDeclarationCesu`
  (one accessor per use case). Testing simulator (`src/Testing`): `SimulatedCesuGateway`, an in-memory CESU platform for
  host test suites (scripted failures, refusals and lost answers, call inspection).
- Symfony bundle (`UrssafTierceDeclarationCesuBundle`, Symfony 6.4 and 7.x) and Flex recipe: configuration tree,
  gateway, token pool, persistence drivers, outbox sinks, `urssaf:cesu:*` console commands, Messenger messages, locks,
  Scheduler schedule, installation diagnostics, English / French translations of the violation codes, and
  `bin/build-flex-endpoint` (ADR 0013).
- Doctrine bridge (ORM 3 / DBAL 4): XML mapping (tables `urssaf_cesu_*`), DBAL types, repositories with atomic claims and
  optimistic locking, outbox, workflow statistics, transaction manager; `withDoctrine()` for the standalone builder; the
  Symfony `doctrine` persistence driver with a database schema check. No IBAN, BIC nor NIR column (ADR 0007, 0014).
- Sandbox contract tests (`tests/Contract`, `make test-contract`, manual CI job `contract`): read-only checks and
  opt-in writes (`URSSAF_SANDBOX_ALLOW_WRITES=1`), a redacted observation report (`var/contract/sandbox-report.json`)
  for the open points of api-reference §5, optional `URSSAF_SANDBOX_TOKEN_URI`.
- GDPR employer erasure (ADR 0015): `ForgetEmployer` / `ForgetEmployerHandler` erase an employer account, its mandates
  and its declaration records from the local store in one transaction (`EmployerErasure` lists the erased ids), refuse
  the erasures that would break the workflow guarantees with `EmployerErasureRefused` (submission in flight, unknown
  outcome, active mandate; the two opt-ins default to `WorkflowPolicy`, `workflow.erasure.allow_unknown_outcomes` and
  `workflow.erasure.allow_active_mandate` in the Symfony bundle), record the integration event
  `urssaf.cesu.employer.forgotten` and never call URSSAF. Standalone accessor `forgetEmployer()` and console command
  `urssaf:cesu:employer:forget`.

### Changed

- Custom repositories (`custom` persistence driver) must implement `MandateRepository::delete()`,
  `DeclarationRecordRepository::countByEmployer()` and `DeclarationRecordRepository::deleteByEmployer()`.
- CI: verifiable quality proofs (Codecov, Stryker Dashboard, SonarQube Cloud, OpenSSF Scorecard; active once the
  repository is public), every GitHub Action pinned to a commit SHA and kept up to date by Dependabot, hadolint on the
  Dockerfile (`make hadolint`, part of `make ci`) with a non-root development image, and benchmarks with PHPBench
  (`make bench`, CI job `benchmark`, isolated in `tools/phpbench`).
- CI: the SonarQube Cloud analysis also requires the repository variable `SONARCLOUD=enabled`, set once the
  project is imported on SonarQube Cloud.
- The package is open source under the MIT license (previously proprietary). Added `CONTRIBUTING.md` and
  `SECURITY.md`, and a notice in the README stating that the package is unofficial and not affiliated with URSSAF.
- README: maintainer (The Bearded Bear), the other URSSAF packages and professional support, architecture overview,
  quick starts (standalone and Symfony) and the sandbox contract test variables; composer `authors` now points to
  https://the-bearded-bear.com.
- PHPStan analyses against the lowest supported PHP version (`phpVersion: 80200` in `phpstan.neon.dist`, composer
  `php` is `>=8.2`), so that PHP 8.3+ runtimes do not demand typed class constants, a feature the package cannot use.
