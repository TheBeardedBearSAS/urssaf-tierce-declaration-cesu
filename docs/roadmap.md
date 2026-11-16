# Roadmap and status

Specification: [api-reference.md](api-reference.md). Decisions: [adr/](adr/README.md). Architecture, conventions and
quality gates are the same as in `thebeardedbear/urssaf-tiers-prestations` (hexagonal layers enforced by Deptrac,
PSR-only runtime, Symfony and Doctrine bridges, 100 % coverage and MSI).

Status: **implemented, not released on Packagist yet.** The sandbox contract run that settles the open points of
api-reference §5 has not been done.

## Skeleton: done

- `SpecVersion::CURRENT` (`2.0.2`), `Infrastructure\Urssaf\Environment` (Sandbox / Production) and
  `Infrastructure\Urssaf\UrssafEndpoints` (token path, scope `tiersdecl.cesu`, base path `/tiersdecl/v1/cesu`).
- Docker tooling, `make ci` quality gate, GitHub Actions, MIT license, `CONTRIBUTING.md`, `SECURITY.md`.

## Domain (`src/Domain`): done

- Shared value objects (`TenantId`, `ExternalReference`, `CalendarDate`, `Instant`, `Money` in cents, `HalfDays`,
  e-mail, phone, country code), `Employer\CesuNumber`, exception hierarchy and the validation notification pattern.
- Employers: `NewEmployer` (010 input), `Address`, bank details, `EmployerStatus` (020 snapshot), `EmployerAccount`
  with `EmployerStage`. Employees: `EmployeeReference` sum type (Cesu number, NIR, administrative data).
- Declarations: `Declaration` sum type (`StandardDeclaration`, `CesuPlusDeclaration`), incomes, supplements, paid leave,
  foster carer approval, `DeclarationResult`, `DeclarationRecord` with `DeclarationState`; contribution estimate.
- `Mandate` with `MandateState`; drafts and factories reporting every violation; the local pre-checks of ADR 0009.
- ADR 0004, 0006, 0007, 0009.

## Application (`src/Application`): done

- Ports: `CesuGateway` split per operation (010 to 070) with its DTOs, `CredentialsProvider`, `TokenStore`, `Clock`,
  `IdGenerator`, `RateLimiter`, `EventPublisher`, `Outbox`, `IntegrationEventSink`, transaction manager, repositories,
  `WorkflowStatistics`; error classification (`ErrorKind`, `BusinessError`).
- Use cases: `RegisterEmployer`, `ImportEmployer`, `RegisterMandate`, `CancelMandate`, `SyncEmployerStatus` /
  `SyncEmployerStatuses` / `SyncAllEmployerStatuses`, `ActivateCesuPlus`, `ActivateAici`, `DeclareSalary` (idempotent,
  mandate guard, atomic claim, unknown outcomes never resent), `ResolveDeclarationOutcome`,
  `ReconcileStaleDeclarations`, `RequeueAfterInvalidCredentials`, `RelayOutbox`, `PurgeOutbox`; queries
  `VerifyEmployer`, `VerifyEmployee`, `EstimateContributions`, `GetEmployerAccount`, `GetDeclaration`,
  `FindDeclarations`, `GetWorkflowOverview`.
- `WorkflowPolicy`, `MandateGuard`, `UnitOfWork` (domain events after commit, integration events through the outbox).
- Public integration events `urssaf.cesu.*` (`Contract\Event`, ADR 0012), in-memory repositories and outbox.
- ADR 0003, 0005, 0010, 0011, 0012; [workflow.md](workflow.md), [integration-events.md](integration-events.md).

## Infrastructure (`src/Infrastructure`): done

- `Urssaf\UrssafHttpGateway` (built by `UrssafGatewayFactory`) over PSR-18 / PSR-17: OAuth2 client credentials with a
  shared token cache, one replay after a 401, `Retry-After`, status mapping and unknown outcomes (ADR 0002, 0003).
- `Urssaf\Wire\*`: date-only dates (ADR 0001), cents, half-days, `accord` encoding (ADR 0008), request and response
  mappers of every operation, error payload parser, `ErrorClassifier`.
- Adapters: in-memory, PSR-6 and PSR-16 token stores, PSR-20 and frozen clocks, UUID v4 generator,
  `SlidingWindowRateLimiter` and no-op limiter, in-memory credentials provider, PSR-3 failure reporter.

## Standalone and testing: done

- `Standalone\UrssafTierceDeclarationCesuBuilder` and `UrssafTierceDeclarationCesu` (one accessor per use case);
  [standalone.md](standalone.md).
- `Testing\SimulatedCesuGateway`, an in-memory CESU platform for host test suites; [testing.md](testing.md).

## Symfony bridge: done

- `UrssafTierceDeclarationCesuBundle` (Symfony 6.4 and 7.x): configuration tree, HTTP gateway, token pool, persistence
  drivers, outbox sinks, `urssaf:cesu:*` console commands, Messenger messages, locks, Scheduler schedule,
  installation diagnostics, validation and translations (English / French).
- Symfony Flex recipe (`recipe/`) and `bin/build-flex-endpoint`; ADR 0013; [symfony.md](symfony.md).

## Doctrine bridge: done

- XML mapping (tables `urssaf_cesu_*`), DBAL types, repositories with atomic claims and optimistic locking, outbox,
  workflow statistics, transaction manager, `withDoctrine()` for the standalone builder and the `doctrine` persistence
  driver of the bundle. No IBAN, BIC nor NIR column. ADR 0014; [doctrine.md](doctrine.md).

## Contract tests: done (not run)

- `make test-contract` and the manual CI job `contract`: read-only checks by default, opt-in writes
  (`URSSAF_SANDBOX_ALLOW_WRITES=1`), a redacted observation report (`var/contract/sandbox-report.json`) with one id per
  open point of api-reference §5. Skipped without credentials.

## GDPR erasure: done

- `ForgetEmployer` use case ([ADR 0015](adr/0015-employer-erasure.md)): the employer account, its mandates and its
  declaration records in one transaction, refusals that protect the workflow (submission in flight, unknown outcome,
  active mandate) with configurable opt-ins, nothing sent to URSSAF.
- Ports: `MandateRepository::delete()`, `DeclarationRecordRepository::countByEmployer()` / `deleteByEmployer()`.
- Integration event `urssaf.cesu.employer.forgotten`; console command `urssaf:cesu:employer:forget`; `forgetEmployer()`
  accessor; [workflow.md](workflow.md#erasure-gdpr).

## Remaining

1. **Sandbox contract run**: run `make test-contract` (with `URSSAF_SANDBOX_ALLOW_WRITES=1` for the write points)
   against the URSSAF sandbox, then settle the **Open** points of api-reference §5 from the report, turning each
   assumption into a decision (ADR) or a code change (for instance `agreement_encoding`, the token host, the
   `etatCesuPlus` spellings).
2. **Packagist release**: first tag, `SpecVersion` check, release notes from the changelog.
3. **Recipe submission** to `symfony/recipes-contrib`.

## Out of scope

CESU, PAJE and Auto-entrepreneur third-party declaration APIs share `/tiersdecl/v1` and the mandate model: a shared
library (OAuth2, mandate, error model) may be extracted later, not now.
