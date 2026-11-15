# Testing a host application

The package ships `TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway`, an in-memory URSSAF CESU
platform: it implements the gateway port (`Application\Port\Urssaf\CesuGateway`), keeps the employers, the mandates, the
Cesu+ and AICI activations and the declarations, and answers the nine operations like the real API, without any network
call. Use it to test your integration (controllers, listeners, crons, Messenger handlers) end to end; keep the sandbox for
the contract tests.

It must never be used outside tests: nothing it accepts is sent to URSSAF.

## Scoping

- Employers are global: one Cesu registry, keyed by Cesu number, like URSSAF.
- A mandate is exclusive: at most one holder tenant per employer. Another tenant gets `COMPTE_DEJA_MANDATE`, and the
  `mandatPresent` of method 020 is true only for the holder.
- Declarations (and the duplicate rule), Cesu+ pairs and AICI are employer-level state, visible to every tenant.

Refusals are built by the same classifier as the real gateway (ADR 0005): `kind()`, `businessError()` and the `is*()`
predicates of `UrssafRequestRejected` behave exactly as in production.

## Default behaviour

| Method | Default answer |
|---|---|
| 010 register employer | Accepted: a new employer is registered (birth date of the draft, online account and SEPA mandate `actif`, no mandate, no Cesu+, no AICI) and its Cesu number is returned (`SIM00000000001`, `SIM00000000002`...). |
| 020 verify employer | Unknown Cesu number: `inscrit` false, every other field empty. Birth date that differs: refused, `ERR_METIER` / `BirthDateMismatch`. Otherwise the account states (default `actif` / `actif`), `mandatPresent` (true for the mandate holder only), `cesuPlus` `active` when the verification names an employee whose pair is active, and `aiciActive`. |
| 021 verify employee | An employee known by `knowEmployee()` (by its Cesu number, or by one of its alias references of the same kind and value) is registered with its Cesu number; any other is not registered. |
| 030 register mandate | Unknown Cesu number or birth date that differs: `ER_API_020_0000`. Mandate held by another tenant: `COMPTE_DEJA_MANDATE`. Otherwise the tenant becomes the holder (re-posting by the holder is accepted, nothing changes). |
| 031 cancel mandate | Same refusals as 030. Otherwise the mandate is removed (cancelling without mandate is accepted, nothing changes). |
| 040 estimate | The current estimate, default 63.10 EUR employer and 20.09 EUR employee contributions (see `setEstimate()`). |
| 050 declare | No mandate held by the tenant (unknown employer included): `MANDAT_INEXISTANT`. A recorded declaration with the same employer, the same employee reference (same kind and value: a Cesu-number reference and a NIR reference of the same person are not matched) and the same employment period: `ERR_METIER` / `DuplicateDeclaration`, whatever the tenant. Otherwise accepted with a new declaration id (`SIM-DECL-000001`, `SIM-DECL-000002`...) and the amounts of the declaration result (default: the estimate contributions, other amounts empty; see `setDeclarationResult()`). |
| 060 activate Cesu+ | Unknown employer or birth date that differs: `ERR_METIER` / `EmployerCesuNumberUnknown`. No mandate held by the tenant: `MANDAT_INEXISTANT`. Pair already active: `ERR_METIER` / `CesuPlusAlreadyActive`. Otherwise the pair becomes active. |
| 070 activate AICI | Unknown employer or birth date that differs: `ERR_METIER` / `EmployerNotFound`. No active Cesu+ pair for the employer: `ERR_METIER` / `CesuPlusNotActive`. Otherwise AICI becomes active (already active: unchanged). |

The other rules of the real platform (`SEPA_ABSENT`, e-mail uniqueness, `RIB_ABSENT`, `AICI_NON_ACTIVE`, the content rules
of method 050, the agreement and blocking rules of method 060...) are not simulated: script them with `refuseNext()` or
`refuseNextWith()`.

A call is processed in this order: it is recorded; a scripted failure is thrown; a scripted refusal replaces the built-in
rules; a lost answer then throws `OutcomeUnknown` whatever the outcome.

## Scripting

```php
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;

$urssaf = new SimulatedCesuGateway();

$urssaf->knowEmployer($employer);                                   // an existing Cesu account (no call recorded)
$urssaf->mandate($employer->cesuNumber(), $tenantId);               // mandate obtained outside the package
$urssaf->withdrawMandate($employer->cesuNumber());                  // e.g. cancelled by the employer
$urssaf->setAccountStates($employer->cesuNumber(), 'radie', 'absent'); // raw 020 states
$urssaf->knowEmployee($employeeCesuNumber, $nirReference);          // 021: by Cesu number or by alias
$urssaf->setEstimate($estimate);                                    // 040, until changed
$urssaf->setDeclarationResult($result);                             // 050 amounts (its declaration id is ignored)

$urssaf->failNext(Operation::DeclareSalary, UrssafUnavailable::withStatus(Operation::DeclareSalary, 503, 30, ApiErrors::none()));
$urssaf->refuseNext(Operation::DeclareSalary, 'RIB_ABSENT', 'Absence de RIB', null);               // HTTP 400, classified from the code
$urssaf->refuseNextWith(Operation::DeclareSalary, BusinessError::CesuPlusDeadlineExceeded);       // ERR_METIER with the catalogue text
$urssaf->loseNextAnswer(Operation::DeclareSalary);                  // processed, but the answer is lost
```

- `failNext()` throws before anything is processed (use it for `UrssafAuthenticationFailed::withStatus(401)`, or for an
  `OutcomeUnknown` meaning "sent but not processed"). Failures and refusals queued for one operation are used in order.
- `refuseNextWith()` throws a `LogicException` when the business error is not documented for the operation.
- `loseNextAnswer()` is for the write operations that can have an unknown outcome (010, 050, 060, 070): the state change
  is kept (the declaration exists, its id is consumed) and `OutcomeUnknown` (HTTP 504) is thrown, the case operators must
  reconcile (ADR 0003). A declaration sent again afterwards is a duplicate. It throws a `LogicException` for the other
  methods.
- `knowEmployer()` throws a `LogicException` when the employer is already known; `mandate()`, `withdrawMandate()` and
  `setAccountStates()` throw one when the employer is unknown.

Identifiers follow the CESU formats: Cesu numbers `SIM%011d` (14 characters), declaration ids `SIM-DECL-%06d`. A declaration
whose answer is lost consumes an id.

## Inspection

```php
$urssaf->calls();                                  // list<Operation>: every call received, in order (failed, refused and lost ones included)
$urssaf->registeredEmployers();                    // list<CesuNumber> registered by method 010
$urssaf->declarationIds();                         // list<DeclarationId> recorded by method 050
$urssaf->isMandated($cesuNumber, $tenantId);       // bool
$urssaf->isCesuPlusActive($cesuNumber, $employeeCesuNumber); // bool
$urssaf->isAiciActive($cesuNumber);                // bool
```

The inspection never exposes personal data (ADR 0007): operations, Cesu numbers, declaration ids and booleans only.

## Without Symfony

```php
use TheBeardedBear\UrssafTierceDeclarationCesu\Standalone\UrssafTierceDeclarationCesuBuilder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;

$urssaf = new SimulatedCesuGateway();
$package = UrssafTierceDeclarationCesuBuilder::create($transport, $credentialsProvider, $configuration)
    ->withGateway($urssaf)
    ->build();
// Or pass $urssaf wherever a handler expects the CesuGateway (or one of its ports).
```
