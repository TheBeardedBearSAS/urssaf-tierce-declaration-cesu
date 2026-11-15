# URSSAF "API Tierce Déclaration CESU" for PHP

<!-- the-bearded-bear:badges:start -->
[![CI](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu/actions/workflows/ci.yml?query=branch%3Amain) [![Mutation score](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FTheBeardedBearSAS%2Furssaf-tierce-declaration-cesu%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu/main) [![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-cesu&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=TheBeardedBearSAS_urssaf-tierce-declaration-cesu) [![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-cesu&metric=sqale_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tierce-declaration-cesu) [![Reliability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-cesu&metric=reliability_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tierce-declaration-cesu) [![Security](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-cesu&metric=security_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tierce-declaration-cesu) [![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu/badge)](https://scorecard.dev/viewer/?uri=github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](phpstan.neon.dist) [![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
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

> 🚧 **Skeleton — not implemented yet.** This repository currently contains the tooling and the specification only.
> The implementation follows [docs/roadmap.md](docs/roadmap.md).

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
- [docs/roadmap.md](docs/roadmap.md) — planned architecture and order of work.
- [docs/openapi/](docs/openapi/) — official OpenAPI contract(s) from the URSSAF API portal.
- `docs/reference/` — official URSSAF documentation: not versioned (URSSAF documents), download it from the links in [docs/api-reference.md](docs/api-reference.md) into this local, git-ignored folder.
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/td-cesu

## Requirements

PHP 8.2+. Development only needs Docker: every `make` target runs in a container.

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
