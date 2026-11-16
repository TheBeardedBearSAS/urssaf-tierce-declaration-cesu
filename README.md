# URSSAF "API Tierce Déclaration CESU" for PHP

<!-- the-bearded-bear:badges:start -->
[![CI](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu/actions/workflows/ci.yml?query=branch%3Amain) [![Coverage](https://codecov.io/gh/TheBeardedBearSAS/urssaf-tierce-declaration-cesu/branch/main/graph/badge.svg)](https://app.codecov.io/gh/TheBeardedBearSAS/urssaf-tierce-declaration-cesu) [![Mutation score](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FTheBeardedBearSAS%2Furssaf-tierce-declaration-cesu%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu/main) [![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-cesu&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=TheBeardedBearSAS_urssaf-tierce-declaration-cesu) [![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-cesu&metric=sqale_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tierce-declaration-cesu) [![Reliability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-cesu&metric=reliability_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tierce-declaration-cesu) [![Security](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-cesu&metric=security_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tierce-declaration-cesu) [![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu/badge)](https://scorecard.dev/viewer/?uri=github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu) [![OpenSSF Best Practices](https://www.bestpractices.dev/projects/16761/badge)](https://www.bestpractices.dev/projects/16761)
[![Packagist version](https://img.shields.io/packagist/v/thebeardedbear/urssaf-tierce-declaration-cesu)](https://packagist.org/packages/thebeardedbear/urssaf-tierce-declaration-cesu) [![PHP version](https://img.shields.io/packagist/dependency-v/thebeardedbear/urssaf-tierce-declaration-cesu/php)](https://packagist.org/packages/thebeardedbear/urssaf-tierce-declaration-cesu) [![Downloads](https://img.shields.io/packagist/dt/thebeardedbear/urssaf-tierce-declaration-cesu)](https://packagist.org/packages/thebeardedbear/urssaf-tierce-declaration-cesu/stats) [![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](phpstan.neon.dist) [![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
<!-- the-bearded-bear:badges:end -->

`thebeardedbear/urssaf-tierce-declaration-cesu` — client, domain model and Symfony integration for the URSSAF API that
lets a platform act as third-party declarant for a private employer: Cesu enrolment, Cesu+ and immediate tax credit
advance (AICI) activation, contribution estimates, salary declaration with withholding at source, and mandate management.

Namespace: `TheBeardedBear\UrssafTierceDeclarationCesu`.

Developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).

> [!IMPORTANT]
> **Unofficial package.** This project is an independent open-source implementation of a public API. It is not
> developed, endorsed, certified or supported by URSSAF, and it is not affiliated with URSSAF in any way. "URSSAF" is
> used only to name the API this package talks to. Using this package does not replace the URSSAF habilitation
> process; the URSSAF documentation remains the authority on the API, and you remain responsible for the data your
> application sends to URSSAF.

**Status: 0.1.0 released; sandbox contract run still to do (see [docs/roadmap.md](docs/roadmap.md)).** The nine operations, the workflow, the standalone builder, the
Symfony bundle and the Doctrine bridge are in place; the sandbox contract run that settles the open points of the API
reference is still to do. See the architecture decision records in [docs/adr/](docs/adr/).

## Scope

| Method | Operation | Endpoint |
|---|---|---|
| CESU010 | Register an employer | `POST /employeurs` |
| CESU020 | Verify an employer account | `GET /employeurs/{numeroCesu}` |
| CESU021 | Verify an employee account | `GET /salaries` |
| CESU030 | Register a third-party declaration mandate | `POST /mandats` |
| CESU031 | Cancel a mandate | `DELETE /mandats` |
| CESU040 | Estimate contributions | `POST /estimer` |
| CESU050 | Declare a salary (Cesu or Cesu+ with AICI) | `POST /employeurs/{numeroCesu}/declarer` |
| CESU060 | Activate Cesu+ | `POST /employeurs/{numeroCesu}/cesuplus` |
| CESU070 | Activate AICI | `POST /employeurs/{numeroCesu}/aici` |

OAuth2 client credentials, scope `tiersdecl.cesu`, base URL `https://api.urssaf.fr/tiersdecl/v1/cesu` (sandbox:
`https://api-edi.urssaf.fr/tiersdecl/v1/cesu`). Restricted access: subscription through DataPass.

## Documentation

- [docs/api-reference.md](docs/api-reference.md) — technical reference (single source of truth for the package).
- [docs/roadmap.md](docs/roadmap.md) — implementation status and what remains.
- [docs/workflow.md](docs/workflow.md) — employer, mandate and declaration lifecycles, idempotency, retries, monitoring, GDPR erasure.
- [docs/integration-events.md](docs/integration-events.md) — public integration events (`urssaf.cesu.*`) and the outbox.
- [docs/doctrine.md](docs/doctrine.md) — Doctrine persistence: tables, dedicated entity manager, concurrency, personal data and encryption, migrations.
- [docs/standalone.md](docs/standalone.md) — wiring without the Symfony bundle: builder options, scheduling, manual wiring.
- [docs/symfony.md](docs/symfony.md) — Symfony bundle: configuration reference, console commands, Messenger, Scheduler, locks, validation, translations.
- [docs/testing.md](docs/testing.md) — testing a host application with the in-memory simulated gateway.
- [docs/adr/](docs/adr/README.md) — architecture decision records.
- [docs/openapi/](docs/openapi/) — official OpenAPI contract(s) from the URSSAF API portal.
- `docs/reference/` — official URSSAF documentation: not versioned (URSSAF documents), download it from the links in [docs/api-reference.md](docs/api-reference.md) into this local, git-ignored folder.
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/td-cesu

## Installation

```bash
composer require thebeardedbear/urssaf-tierce-declaration-cesu
```

With Symfony Flex, the recipe of the package registers the bundle, copies a commented
`config/packages/urssaf_tierce_declaration_cesu.yaml` (the simulated URSSAF platform in the `test` environment) and adds
`URSSAF_CESU_ENV`, `URSSAF_CESU_CLIENT_ID` and `URSSAF_CESU_CLIENT_SECRET` to `.env`. The recipe is prepared for
[symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) but not published there yet: it will be submitted
there. Until then, do these steps by hand (Quick start B below), or serve the recipe from your own Flex endpoint built by
`bin/build-flex-endpoint` (see [docs/symfony.md](docs/symfony.md#symfony-flex-recipe)).

Then generate the tables of the package (it ships no migration, see [docs/doctrine.md](docs/doctrine.md#migrations))
and check the installation:

```bash
php bin/console doctrine:migrations:diff --em=urssaf_cesu && php bin/console doctrine:migrations:migrate
php bin/console urssaf:cesu:check
```

## Architecture

Hexagonal / DDD, the same as `thebeardedbear/urssaf-tiers-prestations` ([ADR 0000](docs/adr/0000-reference-architecture.md)).
Layers are enforced by Deptrac (`deptrac.yaml`):

```
            +-----------------------------------------------+
            | Bridge (Symfony, Doctrine)  - framework glue  |
            +----------------------+------------------------+
                                   |
            +----------------------v------------------------+
            | Infrastructure - HTTP gateway, OAuth2, wire   |
            | encoding, token stores, clock, ids, in-memory |
            +----------------------+------------------------+
                                   | implements ports
            +----------------------v------------------------+
            | Application - commands, queries, handlers,    |
            | ports (CesuGateway, repositories...), DTOs    |
            +----------------------+------------------------+
                                   |
            +----------------------v------------------------+
            | Domain - aggregates, value objects, rules     |
            | (pure PHP, no PSR, no framework)              |
            +-----------------------------------------------+
```

- **Domain**: employers, mandates, declarations (sum type `StandardDeclaration|CesuPlusDeclaration`), value objects
  (`CalendarDate`, `Money` in cents, `Address`...), validation, domain events.
- **Contract**: public, versioned integration events relayed through the outbox.
- **Application**: one `final readonly` handler per use case, ports implemented by the outer layers.
- **Infrastructure**: `UrssafHttpGateway` (implements `CesuGateway`), OAuth2 token provider, JSON client, adapters.
- **Standalone** / **Testing**: framework-free builder and an in-memory simulated gateway for host test suites.
- **Bridge**: `Bridge\Symfony` (bundle, commands, Messenger, Lock, Scheduler) and `Bridge\Doctrine` (XML mapping, DBAL
  types, repositories with atomic claims, optimistic locking, outbox, statistics, transaction manager; no IBAN, BIC nor
  NIR column).

Decisions: [docs/adr/](docs/adr/).

## Quick start A: Standalone (framework-agnostic)

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Standalone\UrssafTierceDeclarationCesuBuilder;

$factory = new Psr17Factory();
$urssaf = UrssafTierceDeclarationCesuBuilder::create(
    new HttpTransport($psr18Client, $factory, $factory),   // any PSR-18 client and PSR-17 factories
    new InMemoryCredentialsProvider(['tenant-1' => Credentials::of((string) getenv('URSSAF_CLIENT_ID'), (string) getenv('URSSAF_CLIENT_SECRET'))]),
    ApiConfiguration::forEnvironment(Environment::Sandbox),
)
    ->withDoctrine($urssafEntityManager)                                               // a dedicated entity manager (docs/doctrine.md); default: in-memory (tests, prototypes)
    ->withTokenStore($sharedTokenStore)                                                 // default: in-memory (a token per process)
    ->build();

$tenant = TenantId::fromString('tenant-1');
$employer = EmployerSelector::byExternalReference(ExternalReference::fromString('employer-42'));
// 1. Enrol the employer (method 010, synchronous; bank details are never stored). $draft is a Domain\Employer\Input\NewEmployerDraft.
$urssaf->registerEmployer()(new RegisterEmployer($tenant, ExternalReference::fromString('employer-42'), $draft));
// 2. Notify the third-party declaration mandate (method 030), required before any declaration (ADR 0009).
$urssaf->registerMandate()(new RegisterMandate($tenant, $employer));
// 3. Declare a salary (method 050, idempotent per external reference). $declaration is a Domain\Declaration\Input\DeclarationDraft.
$submission = $urssaf->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-2026-10'), $declaration));
// $submission->outcome: declared, rejected, already_declared, outcome_unknown (never resent: resolveDeclarationOutcome())...
```

The other use cases are one accessor away: `verifyEmployer()`, `verifyEmployee()`, `syncEmployerStatus()`,
`estimateContributions()`, `activateCesuPlus()`, `activateAici()`, `getEmployerAccount()`, `findDeclarations()`, `forgetEmployer()` (GDPR erasure)... Every
option of the builder and the manual wiring are described in [docs/standalone.md](docs/standalone.md); test your
integration with the simulator ([docs/testing.md](docs/testing.md)).

## Quick start B: Symfony

Once the recipe is published in symfony/recipes-contrib, Symfony Flex will register the bundle, copy the configuration
and add the variables to `.env`:

```sh
composer require thebeardedbear/urssaf-tierce-declaration-cesu
```

Without the recipe (not published yet), do it by hand. Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\UrssafTierceDeclarationCesuBundle::class => ['all' => true],
];
```

Configure it in `config/packages/urssaf_tierce_declaration_cesu.yaml` (every option is described in
[docs/symfony.md](docs/symfony.md)):

```yaml
framework:
    cache:
        pools:
            urssaf_cesu.token_cache:            # the access tokens, shared by every process (web, workers, cron)
                adapter: cache.adapter.redis
                provider: '%env(REDIS_DSN)%'

urssaf_tierce_declaration_cesu:
    environment: '%env(URSSAF_CESU_ENV)%'      # sandbox | production
    credentials:
        default:                                # one entry per platform account (tenant)
            client_id: '%env(URSSAF_CESU_CLIENT_ID)%'
            client_secret: '%env(URSSAF_CESU_CLIENT_SECRET)%'
    token_store: { cache_pool: urssaf_cesu.token_cache }
    persistence: { driver: doctrine, entity_manager: urssaf_cesu }   # a dedicated entity manager; default driver when doctrine/orm is installed
    # persistence: { driver: custom, employer_account_repository: ..., mandate_repository: ..., declaration_record_repository: ... }

when@test:
    urssaf_tierce_declaration_cesu:
        gateway: { simulated: true }            # the in-memory URSSAF platform: nothing is sent to URSSAF
```

and set the variables in `.env.local` (never commit the secret):

```dotenv
URSSAF_CESU_ENV=sandbox
URSSAF_CESU_CLIENT_ID=
URSSAF_CESU_CLIENT_SECRET=
```

The use case handlers are public services, ready to be injected:

```php
#[Route('/declarations/{reference}', methods: ['PUT'])]
public function declare(string $reference, #[MapRequestPayload] DeclarationDraft $draft, DeclareSalaryHandler $declareSalary): Response
{
    $submission = $declareSalary(new DeclareSalary(TenantId::fromString('default'), ExternalReference::fromString($reference), $draft));

    return $this->json(['state' => $submission->outcome->value]);
}
```

Check the installation, then run the safety commands from cron or the Symfony Scheduler:

```sh
php bin/console urssaf:cesu:check
php bin/console urssaf:cesu:employers:sync-status
```

Create the tables with your migrations (the package ships none): `php bin/console doctrine:migrations:diff` then
`doctrine:migrations:migrate` ([docs/doctrine.md](docs/doctrine.md)). Without Doctrine, the `in_memory` persistence loses
every record when the process ends: give your own repositories (`custom` driver), see
[docs/symfony.md](docs/symfony.md#persistence).

## Requirements

PHP 8.2+. Symfony 6.4 / 7.x for the bundle (optional). Doctrine persistence (optional): `doctrine/orm` 3.5.3+, `doctrine/dbal` 4.0+ and, with Symfony, `doctrine/doctrine-bundle` 2.19+. Development only needs Docker: every `make` target runs in a container.

## Development

```sh
make install     # build the image, install dependencies
make ci          # full quality gate: cs, rector, phpstan, mago, deptrac, composer checks, hadolint, guard, coverage, infection
make test        # unit + functional tests
make bench       # benchmarks (PHPBench, tests/Benchmark), added with the implementation
make help        # every target
```

`make ci PHP_VERSION=8.4` runs the gate on another PHP version.

Once the repository is public, `main` publishes its quality proofs, linked from the badges at the top of this file: the
coverage report on Codecov, the full mutation report on the Stryker Dashboard, the SonarQube Cloud quality gate and the
OpenSSF Scorecard. The benchmarks (`tests/Benchmark`, PHPBench in `tools/phpbench`, out of the package dependencies)
run on every pull request against the base commit, and the job fails on a regression of more than 20 %.
## Sandbox contract tests

`make test-contract` runs the `contract` suite against the URSSAF sandbox. It is opt-in: without credentials every test is
skipped and the command exits 0. The suite is never part of the default run, of the coverage or of the mutation testing.
Variables (environment of the shell running `make`, or secrets / variables of the `urssaf-sandbox` environment for the
manual `contract` CI job):

| Variable | Required | Meaning |
|---|---|---|
| `URSSAF_SANDBOX_CLIENT_ID` | yes | OAuth2 client id of your sandbox subscription (secret) |
| `URSSAF_SANDBOX_CLIENT_SECRET` | yes | OAuth2 client secret (secret) |
| `URSSAF_SANDBOX_BASE_URI` | no | sandbox host override (default `https://api-edi.urssaf.fr`); the production host is refused |
| `URSSAF_SANDBOX_TOKEN_URI` | no | full token URL used instead of `<base>/api/oauth/v1/token` (open point 5.14 of the API reference) |
| `URSSAF_SANDBOX_EMPLOYER_CESU_NUMBER` | no | a sandbox employer Cesu number (enables the employer tests) |
| `URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE` | with the above | its birth date, `YYYY-MM-DD` |
| `URSSAF_SANDBOX_EMPLOYEE_CESU_NUMBER` | no | a sandbox employee Cesu number (enables the pair and employee tests) |
| `URSSAF_SANDBOX_ALLOW_WRITES` | no | exactly `1` to also run the tests that write sandbox state |

Read-only by default: token, 020, 021 and 040 (official example, nothing is stored). `URSSAF_SANDBOX_ALLOW_WRITES=1`
also runs 010 (a fictitious employer), 030/031 (the mandate of your test employer, restored afterwards), 050 (one
declaration of the previous month plus its duplicate), 060 (activates Cesu+ for your test pair) and 070 (on the
fictitious employer). These create sandbox state that cannot be undone.

Report: `var/contract/sandbox-report.json` (git-ignored) lists what the sandbox answered for each open point of
[docs/api-reference.md](docs/api-reference.md) section 5: enum values, key names, JSON types and HTTP statuses only,
no personal data, no token. The CI job uploads it as the `contract-report` artifact.

CI: Actions > CI > Run workflow, tick `contract` (and `contract_writes`); environment `urssaf-sandbox`.

Never commit these values. Do not set `failOnSkipped` / `failOnIncomplete` in `phpunit.xml.dist`: the suite must pass
when skipped. The 050 test targets the previous calendar month: the `idDeclaration` type is observable once per
employer / employee pair and month, later runs see the duplicate refusal.

## Versioning

Semantic Versioning. `SpecVersion::CURRENT` holds the version of the official URSSAF documentation implemented.

<!-- the-bearded-bear:start -->
## Other URSSAF packages

The same architecture, quality gate and conventions, one package per URSSAF API (overview: [the-bearded-bear.com/open-source/urssaf](https://the-bearded-bear.com/open-source/urssaf)):

| API | Package | Status |
|---|---|---|
| Tiers de prestations (immediate tax-credit advance) | [`thebeardedbear/urssaf-tiers-prestations`](https://github.com/TheBeardedBearSAS/urssaf-tiers-prestations) | Released (Packagist) |
| Attestations Vérification Code Sécurité (certificate verification) | [`thebeardedbear/urssaf-attestations-verification-code-securite`](https://github.com/TheBeardedBearSAS/urssaf-attestations-verification-code-securite) | Released (Packagist) |
| DPAE (pre-employment declaration) | [`thebeardedbear/urssaf-dpae`](https://github.com/TheBeardedBearSAS/urssaf-dpae) | Coming soon |
| Mobilité internationale (posting of workers abroad) | [`thebeardedbear/urssaf-mobilite-internationale`](https://github.com/TheBeardedBearSAS/urssaf-mobilite-internationale) | Coming soon |
| Plateforme numérique (withholding of contributions by digital platforms) | [`thebeardedbear/urssaf-plateforme-numerique`](https://github.com/TheBeardedBearSAS/urssaf-plateforme-numerique) | Coming soon |
| Tierce Déclaration auto-entrepreneur | [`thebeardedbear/urssaf-tierce-declaration-auto-entrepreneur`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-auto-entrepreneur) | Coming soon |
| Tierce Déclaration PAJE | [`thebeardedbear/urssaf-tierce-declaration-paje`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje) | Coming soon |

## Professional support

This package is developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).
If you need help with an URSSAF integration, The Bearded Bear can help with:

- the URSSAF habilitation and homologation process (subscription, sandbox tests, move to production);
- integrating the package into your application (Symfony, Doctrine or framework-free);
- support with a guaranteed response time, including the follow-up of URSSAF API changes;
- custom developments on top of the package.

Project page and integration offer: [the-bearded-bear.com/open-source/urssaf-tierce-declaration-cesu](https://the-bearded-bear.com/open-source/urssaf-tierce-declaration-cesu).
<!-- the-bearded-bear:end -->

## Contributing and security

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md), never in a public issue.

## License

MIT. See [LICENSE](LICENSE). The software is provided "as is", without warranty of any kind.

The API specification summarised in [docs/api-reference.md](docs/api-reference.md) belongs to URSSAF; see the sources
listed there.
