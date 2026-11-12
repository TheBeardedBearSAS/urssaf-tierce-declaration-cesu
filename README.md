# URSSAF "API Tierce Déclaration CESU" for PHP

`thebeardedbear/urssaf-tierce-declaration-cesu` — client, domain model and Symfony integration for the URSSAF API that
lets a platform act as third-party declarant for a private employer: Cesu enrolment, Cesu+ and immediate tax credit
advance (AICI) activation, contribution estimates, salary declaration with withholding at source, and mandate management.

Namespace: `TheBeardedBear\UrssafTierceDeclarationCesu`.

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
- [docs/reference/](docs/reference/) — official URSSAF documentation.
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/td-cesu

## Requirements

PHP 8.2+. Development only needs Docker: every `make` target runs in a container.

## Development

```sh
make install     # build the image, install dependencies
make ci          # full quality gate: cs, rector, phpstan, mago, deptrac, composer checks, guard, coverage, infection
make test        # unit + functional tests
make help        # every target
```

`make ci PHP_VERSION=8.4` runs the gate on another PHP version.

## Versioning

Semantic Versioning. `SpecVersion::CURRENT` holds the version of the official URSSAF documentation implemented.

## License

Proprietary — see [LICENSE](LICENSE).
