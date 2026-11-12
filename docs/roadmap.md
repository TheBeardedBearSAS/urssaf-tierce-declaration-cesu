# Implementation roadmap

Specification: [api-reference.md](api-reference.md). Architecture, conventions and quality gates are the same as in
`thebeardedbear/urssaf-tiers-prestations` (hexagonal layers enforced by Deptrac, PSR-only runtime, Symfony and
Doctrine bridges, 100 % coverage and MSI).

## Already in place (skeleton)

- `SpecVersion::CURRENT` (`2.0.2`).
- `Infrastructure\Urssaf\Environment` (Sandbox `https://api-edi.urssaf.fr` / Production `https://api.urssaf.fr`) and
  `Infrastructure\Urssaf\UrssafEndpoints` (token path `/api/oauth/v1/token`, scope `tiersdecl.cesu`, base path
  `/tiersdecl/v1/cesu`).

## Domain (`src/Domain`)

- `Employer\CesuNumber` (14 alphanumeric chars), `Employer\Employer` (Cesu number, birth date, registration data),
  `Employer\EmployerStatus` (account state, SEPA mandate state, mandate present, Cesu+ state, AICI flag; string enums with
  an unknown-value fallback, `etatCesuPlus` accepts both Swagger and PDF spellings, api-reference §3.4).
- `Employer\NewEmployer` (010 input: civility, names, birth date, e-mail, phone, address, bank details with SEPA flag),
  `Address` (road rules and 32-character line limit), `BankDetails` (IBAN, BIC), `Civility`.
- `Employee\EmployeeReference` (sum type: Cesu number | NIR | administrative data), `Employee\EmployeeIdentity`,
  `EmploymentKind` (`PE|AF`).
- `Mandate\Mandate` (employer, birth date, platform, notified / cancelled dates) and `Mandate\MandateState`.
- `Declaration\Declaration` (sum type `StandardDeclaration|CesuPlusDeclaration`), `EmploymentPeriod` (same month, not in
  the future), `HourlyIncome` (`RevenusEf`), `FosterCarerIncome` (`RevenusAf`), `SalarySupplement` (+ category and
  sub-category enums, §3.8), `PaidLeavePeriod`, `FosterCarerApproval`, `ActivityNature` (§3.12).
- `Declaration\DeclarationResult` (`idDeclaration`, contributions, PAS, employer debits, gross, net taxable, transfer, AICI),
  `Estimate\ContributionsEstimate`, `Estimate\EstimateRequest` (+ `ExemptionCode`, `GeographicZone`, `SalaryType`).
- `Shared\CalendarDate` (date-only), `Shared\Money` (cents), `Shared\HalfDays`.
- `Domain\Exception\*`.

## Application (`src/Application`)

- Port `Port\Urssaf\CesuGateway`: `registerEmployer`, `verifyEmployer`, `verifyEmployee`, `registerMandate`,
  `cancelMandate`, `estimate`, `declare`, `activateCesuPlus`, `activateAici`.
- Ports `CredentialsProvider`, `TokenStore` (same contracts as Tiers de prestations), plus persistence ports
  `EmployerRepository`, `MandateRepository`, `DeclarationRepository` (tracking, implemented by the Doctrine bridge).
- Commands: `RegisterEmployer`, `RegisterMandate`, `CancelMandate`, `ActivateCesuPlus`, `ActivateAici`,
  `DeclareSalary` (never retried automatically, api-reference §4.5), each with a handler.
- Queries: `VerifyEmployer`, `VerifyEmployee`, `EstimateContributions`, each with a handler.
- Application service `MandateGuard` (refuses 050/060 without a known mandate unless opted out).

## Infrastructure (`src/Infrastructure`)

- `Urssaf\Http\OAuth2TokenProvider`, `JsonApiClient`, `RetryAfter` (reuse the Tiers de prestations design).
- `Urssaf\UrssafHttpGateway` + `Urssaf\Wire\*` mappers (date-only `YYYY-MM-DD`, `accord` boolean, empty-body 200 for 030/031,
  both `etatCesuPlus` spellings, `motifBlocageCesuPlus` casing, cents <-> JSON numbers).
- `Urssaf\Exception\ApiErrorCode` (api-reference §3.13) and error payload parser (single object, tolerant to a list),
  `ERR_METIER` best-effort classifier on `description`, both technical spellings.
- `ApiConfiguration` (environment + overridable base URI, business time zone default `Europe/Paris`).

## Bridges

- Symfony bundle `UrssafTierceDeclarationCesuBundle` (alias `urssaf_tierce_declaration_cesu`): configuration (credentials,
  environment, base URI, business time zone), HTTP client, token cache pool, console commands
  (`urssaf:cesu:verify-employer`, `urssaf:cesu:estimate`, `urssaf:cesu:mandate:register|cancel`).
- Doctrine bridge: tracking of employers (registration data, last known `EmployerStatus`), mandates (notification and
  cancellation dates, last `mandatPresent`) and declarations (request, `idDeclaration`, amounts, outcome including
  *unknown outcome* after a timeout). No personal data beyond what the use case needs; NIR, IBAN and BIC encrypted or
  not stored.

## Runtime dependencies to add with the code

`ext-json`, `ext-mbstring`, `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/clock`, `psr/log`,
`psr/simple-cache` (or `psr/cache`), plus the matching `suggest` entries (Symfony and Doctrine bridges).

## Tests

- Unit: value objects (address line rule, employee identification modes, same-month rule, half-days, cents), mappers
  against `tests/Fixtures/wire/*.json`, handlers with a fake gateway.
- Functional: Symfony bundle wiring, Doctrine mapping.
- Contract (`make test-contract`, manual CI job): sandbox credentials `URSSAF_SANDBOX_CLIENT_ID/SECRET`, optional
  `URSSAF_SANDBOX_BASE_URI`; known employer and employee numbers with birth date (to add as repository variables). First
  goals: settle `etatCesuPlus` spelling, `accord` encoding, sandbox token host and the employee-parameters rule of 020 (api-reference §5).

## Out of scope

CESU, PAJE and Auto-entrepreneur third-party declaration APIs share `/tiersdecl/v1` and the mandate model: a shared
library (OAuth2, mandate, error model) may be extracted later, not now.

## Order of work

1. Domain value objects (dates, money, address, employee reference) and declaration sum type.
2. Wire mappers + fixtures.
3. HTTP gateway (OAuth2, errors, 429 handling, no automatic retry on 010 and 050).
4. Application commands and queries.
5. Symfony bridge.
6. Doctrine bridge (employers, mandates, declarations tracking).
7. Contract tests, then remove the settled open points from `api-reference.md`.
