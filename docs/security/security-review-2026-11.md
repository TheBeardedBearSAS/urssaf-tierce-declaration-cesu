# Security review, November 2026

| | |
|---|---|
| Date | 2026-11-17 |
| Reviewer | The project maintainer, assisted by an AI code assistant (Claude) |
| Scope | `thebeardedbear/urssaf-tierce-declaration-cesu`, commit `71065256017ea0810f60e5396be2e428c77002ea` (`main`, after release v0.1.0): everything under `src/`, the Composer configuration, the GitHub workflows, composite action and Dependabot configuration, and the repository protection settings |
| Reference | [Assurance case](assurance-case.md): security requirements SR1 to SR7, threat model and trust boundaries |
| Next review | At the latest in November 2027, or earlier after a change of a trust boundary |

## Method

1. **Design review against the security requirements and the trust boundaries** of the assurance case: for each
   boundary (host to package, package to URSSAF, package to database, package to cache, package to logs, pipeline to
   consumers) the data crossing it was identified and the control that protects it was located in the code.
2. **Manual code review** of the security-relevant code: credentials and token handling (`Application/Port/Credentials`,
   `AccessToken`, `Infrastructure/Urssaf/Http/OAuth2TokenProvider`, `Infrastructure/TokenStore/*`), the HTTP client and
   URL building (`JsonApiClient`, `ApiConfiguration`, `Environment`, `Wire/ApiPaths`), the decoding and encoding of
   untrusted payloads (`Infrastructure/Urssaf/Wire/*`), validation of employer, employee and declaration data
   (`src/Domain/**`), the declaration workflow (`DeclareSalaryHandler`, `MandateGuard`, `DeclarationSubmitter`, atomic
   claim and unknown outcomes), logging and exception messages (every `logger->` call and exception factory), randomness
   (`Infrastructure/Id/*`), Doctrine repositories, mapping, types and serializers, the GDPR erasure (`ForgetEmployer`),
   the outbox and the integration event codec, the Symfony bundle configuration and registrars, and the diagnostics.
3. **Targeted searches** for dangerous constructs in `src/`: `unserialize`, `serialize`, `eval`, `exec`, weak hashes
   and random functions, TLS options, `http://` literals, raw SQL concatenation, `var_dump`/`print_r`, direct reads
   of the credentials, `getMessage()` calls.
4. **CI and supply-chain review**: the four workflows, the composite setup action, Dependabot configuration, release
   signing, and the settings of the GitHub repository (ruleset of `main`, secret scanning, workflow token defaults),
   read through the GitHub API.
5. **Tool support** (complements, does not replace the human review): the standing gates PHPStan level max, Mago,
   Deptrac, 100% coverage, Infection at 100% MSI, PHP-CS-Fixer and Rector run on every pull request; `composer audit`
   in `make composer-checks`; CodeQL on the workflows, OpenSSF Scorecard and SonarQube Cloud (when enabled) as
   described in the assurance case.

Not done: no dynamic testing (fuzzing, penetration test) and no review of the third-party development tools beyond
`composer audit`. The live URSSAF sandbox was not exercised (the sandbox contract run is still to do, see
[ROADMAP.md](../../ROADMAP.md)).

## Result

No vulnerability exploitable in the default or the documented configuration was found. The review recorded fifteen
observations, all low or informational, listed below. The main design properties were confirmed: no third-party runtime
code, secrets never logged nor serialised, strict validation at construction, strict typed decoding of responses, exact
money representation, no IBAN, BIC, NIR, name, address, e-mail or phone in the database, a mandate required before a declaration,
unknown outcomes never resent, consistent tenant scoping in the queries, pinned and least-privilege CI.

## Findings

Severity scale: Critical, High, Medium, Low, Informational. Status: Open (accepted for a later hardening change),
Accepted (residual risk assumed by design or by the host), Mitigated.

| ID | Severity | Description | Status |
|---|---|---|---|
| SR-01 | Low | The base URI override (`ApiConfiguration`, bundle option `base_uri`) accepts any `http` or `https` URI (`BASE_URI_PATTERN`): an `http://` URL would send the client secret and token in clear. The default environments are HTTPS and the override is an operator setting, but a guard (refuse non-HTTPS outside a loopback host) is cheap. Redirect handling and TLS verification are those of the PSR-18 client provided by the host; the package neither enables nor disables them. | Open |
| SR-02 | Low | The response body is read in full (`(string) $response->getBody()` in `JsonApiClient` and `OAuth2TokenProvider`) and decoded with a depth limit of 512, without a size cap. A malicious or compromised endpoint could force a large allocation. Mitigated by the client timeout and PHP `memory_limit`; the cap would have to be applied on the stream. | Open |
| SR-03 | Low | The outbox stores integration events with PHP `serialize()` and reads them with `unserialize()` restricted by `allowed_classes` to the ten scalar event classes (`IntegrationEventCodec`). Not exploitable through URSSAF input, and an attacker able to write the outbox table already controls the database, but a plain JSON codec would remove the class of risk and the coupling of stored data to class layout. | Open |
| SR-04 | Low | `PsrLoggerFailureReporter` logs, and `RelayOutboxHandler` stores in the `last_error` column of the outbox (`DoctrineOutbox::markFailed()`), the `Throwable::getMessage()` of a failure raised by a host listener or sink. The package's own messages are free of secrets and personal data, but a host exception message is outside its control. Logging the exception class only (as elsewhere in the package) would be consistent. | Open |
| SR-05 | Informational | URSSAF error `message` and `description` are stored verbatim in the declaration records (submission errors) and exposed in views. They are not logged and not part of exception messages, but URSSAF could echo submitted values (employee identity, amounts) in them. Covered by the host retention policy and by `ForgetEmployer`; ADR 0007 documents it. | Accepted |
| SR-06 | Informational | The access token is stored in clear in the PSR-6 / PSR-16 pool for its lifetime (default pool `cache.app`, often a filesystem directory). Short lived and bound to one client id, but the host must keep the pool private. Listed in the assumptions of the assurance case. | Accepted |
| SR-07 | Informational | The employer birth date, the Cesu number and the amounts of the declaration results (gross and net taxable salary, contributions, tax credit) are stored in clear columns by default. IBAN, BIC, NIR and employee identity are never stored. Encryption of the birth date (own DBAL type) and of the results through replaceable DBAL types is documented (`docs/doctrine.md`); database encryption at rest, access control and backup retention belong to the host. | Accepted |
| SR-08 | Informational | `client_secret` handling depends on the host: `%env()%` placeholders are documented, but a literal value in committed configuration is accepted by the bundle and would end in the compiled container. A host-side secret store is assumed. | Accepted |
| SR-09 | Informational | The default rate limiter is per process (`SlidingWindowRateLimiter`): several workers can together exceed the URSSAF quotas. A shared limiter can be injected (`GatewayOptions`); this is an availability concern, not a confidentiality or integrity one. | Accepted |
| SR-10 | Low | Repository settings: the ruleset of `main` requires pull requests with resolved review threads, 19 status checks, forbids deletion and force-push and has no bypass actor, but requires 0 approving reviews (single maintainer), does not require the branch to be up to date, signed commits or a linear history, and does not yet list the `dco` job (and, as it does not run on pull requests, `coverage-branches`) among the required checks. Dependabot security updates are disabled (Dependabot version updates for Composer and GitHub Actions are configured, and `composer audit` runs in CI). The default workflow token is read-only, but workflows are allowed to approve pull requests. | Open |
| SR-11 | Low | Hardening of the CI: the checkouts of the `ci.yml` jobs other than `dco` keep the default `persist-credentials: true` (the token is read-only, but the CodeQL, Scorecard, release and DCO checkouts already disable it). The SonarQube Cloud analysis only runs when the `SONARCLOUD` repository variable is enabled, so it is not a guaranteed gate. CodeQL does not analyse PHP: PHP code security relies on PHPStan, Mago and SonarQube Cloud. | Open |
| SR-12 | Informational | The NIR (`Nir`: 15 non-blank characters), the IBAN and the BIC (alphanumeric, bounded length) are checked for shape only, with no checksum: URSSAF validates their content and answers a 400 that the package maps to a typed rejection. A malformed value therefore reaches URSSAF instead of being refused locally. Deliberate (api-reference §4.12), no integrity impact. | Accepted |
| SR-13 | Informational | Lookups by aggregate identifier (`EmployerAccountRepository::get()`, `MandateRepository::get()`, `DeclarationRecordRepository::get()`, `delete()`) are not filtered by tenant, whereas every other query is. The identifiers are random UUIDs returned by the package; a host that accepts an identifier from an end user must check the tenant of the loaded aggregate itself. | Accepted |
| SR-14 | Informational | The employer Cesu number is treated as an identifier, not as personal data (`ApiPaths`, ADR 0007): it is part of the logged request path of the operations 020, 050, 060 and 070 and appears in the context of some `InvariantViolation` exceptions (for example `mandate.duplicate_active`). Combined with the host database it identifies an employer, so a host that ships its logs to a third party should treat the path as pseudonymous data. | Accepted |
| SR-15 | Informational | Three regular expressions lack the `D` modifier: `CacheKey::VERBATIM_PATTERN` (the key is built by the package, so a trailing newline cannot come from outside), `RetryAfter` (a trailing newline parses to the same integer) and `BusinessErrorCatalogue::pattern()` (classifies the text of an URSSAF error: a trailing newline can only change the classification of an error, never grant access). The amounts of a response are decoded from PHP floats rounded to cents (`MoneyEncoding::decode()`); they are stored, never sent back. No security decision depends on either. | Accepted |

No finding of severity Medium or higher.

## Confirmed controls (positive results)

- `composer.json` requires only PSR interfaces and PHP extensions at runtime.
- Credentials: private constructor and validation, masked `__debugInfo()`, `#[SensitiveParameter]`, serialisation
  refused (`CredentialsNotSerializable`); the secret is read only to build the token request.
- Logging: only operation, method, path, status, duration, request id and exception class (and counts) are logged by the
  package; the path holds the employer Cesu number only, the query string (employee lookup) is never logged; bodies,
  tokens and personal data are not.
- Exception messages carry codes, statuses and field names, never the rejected value.
- Storage minimisation: the Doctrine mapping has no column for IBAN, BIC, NIR, names, e-mail, phone or address (the
  eleven mapping files list every column; `DoctrineMappingTest` asserts the absence of such columns); `ForgetEmployer` erases the employer, its mandates and
  its declaration records in one transaction, with post-checks and refusals that protect in-flight calls.
- All validation regexes of `src/Domain` are anchored and use the `D` modifier (three patterns elsewhere do not, see
  SR-15); no catastrophic-backtracking pattern was identified.
- Responses are decoded with `json_decode` into arrays and read through typed accessors; an unknown status or invalid
  JSON raises `UnexpectedUrssafResponse`.
- Declarations: required mandate (`MandateGuard`), idempotency per external reference, atomic claim before the call,
  write operations that may have reached URSSAF become `outcome_unknown` and are never resent automatically, exactly
  one replay after a 401.
- Randomness comes from `random_bytes()`; weak generators are absent from `src`.
- Persistence uses named parameters; interpolated identifiers are constants or Doctrine metadata.
- Tenant scoping is applied in the repository queries; locks and token cache keys are per scope or per client.
- The simulated gateway is refused in the production environment at configuration time.
- Workflows: pinned action SHAs, read-only default permissions, scoped elevation, no `pull_request_target`, no
  interpolation of untrusted input in `run` scripts, signed release assets with provenance.

## Residual risks and assumptions on the host

See section 7 of the [assurance case](assurance-case.md#7-assumptions-on-the-host-application): secure storage of the
`client_secret`, a private token cache, TLS and redirect behaviour of the PSR-18 client, database protection and
encryption at rest, backup retention, retention and erasure policy, mandate check kept on and unknown outcomes settled,
and absence of body logging in the host.

## Follow-up

The open findings are tracked as hardening work for the next releases. Vulnerabilities are reported privately, see
[SECURITY.md](../../SECURITY.md).
