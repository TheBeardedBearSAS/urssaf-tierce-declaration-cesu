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
  (ADR 0000) and the CESU-specific decisions (ADR 0001 to 0012).
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
