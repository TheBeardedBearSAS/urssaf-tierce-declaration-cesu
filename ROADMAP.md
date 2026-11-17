# Roadmap

Intended direction for the next twelve months (November 2026 to November 2027). It is a statement of intent, not a
commitment; it is reviewed at each minor release. Proposals are welcome in GitHub issues. The detailed status of each
layer is in [docs/roadmap.md](docs/roadmap.md).

## Planned

- **Settle the open points with the sandbox.** Run the contract suite (`make test-contract`) against the URSSAF sandbox
  and turn every **Open** point of [docs/api-reference.md](docs/api-reference.md) §5 into a decision (ADR) or a code
  change, with upgrade notes in the [changelog](CHANGELOG.md) when behaviour changes.
- **Follow the URSSAF API.** Track each new version of the URSSAF *API Tierce Déclaration CESU* (swagger and technical
  documentation, currently 2.0.2) and release the matching changes.
- **Release 1.0.** Stabilise the public API (domain model, use cases, integration events, Symfony bundle, Doctrine
  integration), then release `1.0.0`. From 1.0 on, breaking changes only happen in major versions, as required by
  [Semantic Versioning](https://semver.org/).
- **Symfony Flex recipe.** Submit the recipe (`recipe/`) to `symfony/recipes-contrib`.
- **Keep up with the platform.** Support new PHP versions (8.5 and later) and the new Symfony and Doctrine versions
  as they are released, and drop the versions that reach their end of life in a minor release, announced in the
  changelog.
- **Keep the quality and security bar.** 100 % coverage and 100 % MSI, branch coverage, PHPStan at level max, signed
  releases, and the OpenSSF Best Practices criteria.

## Not planned

- Other URSSAF APIs: each one gets its own package (see *Other URSSAF packages* in the [README](README.md)).
- A shared library for the three third-party declaration APIs (CESU, PAJE, auto-entrepreneur): it may be extracted
  later, not now (see [docs/roadmap.md](docs/roadmap.md)).
- Official bridges for other frameworks than Symfony: the core works without a framework (see
  [docs/standalone.md](docs/standalone.md)).
