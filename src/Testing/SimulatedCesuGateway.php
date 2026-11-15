<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Testing;

use Closure;
use LogicException;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\ApiErrorCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\BusinessErrorCatalogue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorClassifier;
use Throwable;

/**
 * An in-memory URSSAF CESU platform for the tests of a host application: it keeps the employers, the mandates, the
 * Cesu+ and AICI activations and the declarations, and answers the nine operations like the real API would, without
 * any network.
 *
 * Scoping: employers are global (one Cesu registry); a mandate is exclusive (one holder tenant per employer, the
 * "mandatePresent" of method 020 is relative to the calling tenant); declarations, the duplicate rule, Cesu+ pairs and
 * AICI are employer-level state.
 *
 * Default behaviour (every step can be scripted):
 * - 010: the employer is registered with a new Cesu number (SIM00000000001...), states "actif", no mandate;
 * - 020: an unknown Cesu number answers "not registered"; a birth date that differs is a BirthDateMismatch refusal;
 * - 021: a known employee (see knowEmployee()) is registered, any other is not;
 * - 030 / 031: unknown employer or birth date: ER_API_020_0000; mandate held by another tenant: COMPTE_DEJA_MANDATE;
 * - 040: the current estimate (default 63.10 EUR employer, 20.09 EUR employee contributions);
 * - 050: no mandate for the tenant: MANDAT_INEXISTANT; same employer, employee reference and period already
 *   declared: DuplicateDeclaration; otherwise accepted with a new id (SIM-DECL-000001...);
 * - 060: unknown employer: EmployerCesuNumberUnknown; no mandate: MANDAT_INEXISTANT; pair already active:
 *   CesuPlusAlreadyActive;
 * - 070: unknown employer: EmployerNotFound; no active Cesu+ pair: CesuPlusNotActive.
 *
 * Rejections are classified by the real ErrorClassifier (ADR 0005). Never use it outside tests: nothing is sent to
 * URSSAF.
 *
 * Implements the nine operations plus the scripting and inspection API, hence the method count and complexity.
 *
 * Source: reference `SimulatedUrssafGateway`, lot 5 design §4, docs/api-reference.md §1.4 and §2.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class SimulatedCesuGateway implements CesuGateway
{
    public const DEFAULT_EMPLOYER_CONTRIBUTIONS_CENTS = 6310;

    public const DEFAULT_EMPLOYEE_CONTRIBUTIONS_CENTS = 2009;

    private readonly SimulatedScript $script;

    private readonly SimulatedSequence $cesuNumbers;

    private readonly SimulatedSequence $declarationIds;

    private ContributionsEstimate $estimate;

    private DeclarationResult $declarationResult;

    /** @var array<string, SimulatedEmployer> by Cesu number */
    private array $employers = [];

    /** @var array<string, array{CesuNumber, array<array-key, EmployeeReference>}> known employees (Cesu number and aliases), keyed by Cesu number */
    private array $employees = [];

    /** @var list<SimulatedDeclaration> */
    private array $declarations = [];

    /** @var list<CesuNumber> */
    private array $registered = [];

    /** @var list<Operation> */
    private array $calls = [];

    public function __construct()
    {
        $this->script = new SimulatedScript();
        $this->cesuNumbers = new SimulatedSequence('SIM%011d');
        $this->declarationIds = new SimulatedSequence('SIM-DECL-%06d');
        $contributions = Contributions::of(
            Money::fromCents(self::DEFAULT_EMPLOYER_CONTRIBUTIONS_CENTS),
            Money::fromCents(self::DEFAULT_EMPLOYEE_CONTRIBUTIONS_CENTS),
        );
        $this->estimate = ContributionsEstimate::of($contributions);
        $this->declarationResult = new DeclarationResult(
            DeclarationId::fromString('SIM-DECL-000000'),
            $contributions,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }

    /**
     * The next call of the operation throws the failure before anything is processed (for example
     * `UrssafUnavailable::withStatus(...)`, `UrssafAuthenticationFailed::withStatus(401)`, or an `OutcomeUnknown` for
     * "sent but not processed"); failures queued for the same operation are thrown in order.
     */
    public function failNext(Operation $operation, Throwable $failure): void
    {
        $this->script->fail($operation, $failure);
    }

    /**
     * The next call of the operation is refused (HTTP 400) with this error, classified like the real API (ADR 0005);
     * refusals queued for the same operation are answered in order.
     */
    public function refuseNext(Operation $operation, string $code, string $message, ?string $description): void
    {
        $this->script->refuse($operation, ApiErrors::of(ApiError::of($code, $message, $description)));
    }

    /**
     * Shortcut: the next call is refused with the ERR_METIER error documented for the operation (the description is
     * the catalogue text).
     *
     * @throws LogicException when the business error is not documented for the operation
     */
    public function refuseNextWith(Operation $operation, BusinessError $error): void
    {
        if (!\in_array($operation, BusinessErrorCatalogue::operations($error), true)) {
            throw new LogicException(\sprintf(
                'The business error "%s" is not documented for method %s.',
                $error->value,
                $operation->methodNumber(),
            ));
        }

        $this->script->refuse($operation, self::businessErrors($error));
    }

    /**
     * The next call of the write operation is processed (state changed, identifier consumed) but its answer is lost:
     * an OutcomeUnknown (HTTP 504) is thrown, the case operators must reconcile (ADR 0003). "Sent but not processed"
     * is scripted with failNext().
     *
     * @throws LogicException when the operation is safe to retry (it has no unknown outcome)
     */
    public function loseNextAnswer(Operation $operation): void
    {
        if ($operation->isSafeToRetry()) {
            throw new LogicException(\sprintf(
                'Only methods 010, 050, 060 and 070 have an unknown outcome; method %s does not.',
                $operation->methodNumber(),
            ));
        }

        $this->script->loseAnswer($operation);
    }

    /**
     * An existing Cesu employer account (online account and SEPA mandate "actif", no mandate, no Cesu+, no AICI). No
     * call is recorded.
     *
     * @throws LogicException when the employer is already known
     */
    public function knowEmployer(#[SensitiveParameter] Employer $employer): void
    {
        $key = $employer->cesuNumber()->value();
        if (\array_key_exists($key, $this->employers)) {
            throw new LogicException(\sprintf('The employer "%s" is already known to the simulator.', $key));
        }

        $this->employers[$key] = new SimulatedEmployer($employer->cesuNumber(), $employer->birthDate());
    }

    /**
     * The tenant holds the mandate of the employer, obtained outside the package (no call recorded); it replaces any
     * holder.
     */
    public function mandate(CesuNumber $employer, TenantId $tenantId): void
    {
        $this->employer($employer)->holdBy($tenantId);
    }

    /**
     * The mandate disappears at URSSAF (for example cancelled by the employer): method 020 reports no mandate and
     * methods 050 and 060 answer MANDAT_INEXISTANT.
     */
    public function withdrawMandate(CesuNumber $employer): void
    {
        $this->employer($employer)->holdBy(null);
    }

    /**
     * Raw states of the employer account returned by method 020 (unknown values allowed, for example "radie").
     */
    public function setAccountStates(CesuNumber $employer, string $onlineAccountState, string $sepaMandateState): void
    {
        $this->employer($employer)->setStates($onlineAccountState, $sepaMandateState);
    }

    /**
     * An employee known to the Cesu repository (method 021): found by its Cesu number or by any of the given
     * references.
     */
    public function knowEmployee(CesuNumber $cesuNumber, EmployeeReference ...$aliases): void
    {
        $this->employees[$cesuNumber->value()] = [$cesuNumber, $aliases];
    }

    /**
     * Result of the next estimates (method 040), until changed.
     */
    public function setEstimate(ContributionsEstimate $estimate): void
    {
        $this->estimate = $estimate;
    }

    /**
     * Amounts of the next accepted declarations (method 050), until changed; the declaration id of the template is
     * ignored (the identifiers come from the sequence).
     */
    public function setDeclarationResult(DeclarationResult $template): void
    {
        $this->declarationResult = $template;
    }

    /**
     * @return list<Operation> every call received, in order (failed, refused and lost ones included)
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<CesuNumber> the employers registered by method 010, in order
     */
    public function registeredEmployers(): array
    {
        return $this->registered;
    }

    /**
     * @return list<DeclarationId> the declarations recorded (accepted or answer lost), in order
     */
    public function declarationIds(): array
    {
        return array_map(static fn(SimulatedDeclaration $declaration): DeclarationId => $declaration->id, $this->declarations);
    }

    public function isMandated(CesuNumber $employer, TenantId $tenantId): bool
    {
        return ($this->employers[$employer->value()] ?? null)?->isMandatedBy($tenantId) ?? false;
    }

    public function isCesuPlusActive(CesuNumber $employer, CesuNumber $employee): bool
    {
        return ($this->employers[$employer->value()] ?? null)?->isCesuPlusActive($employee) ?? false;
    }

    public function isAiciActive(CesuNumber $employer): bool
    {
        return ($this->employers[$employer->value()] ?? null)?->isAiciActive() ?? false;
    }

    public function registerEmployer(TenantId $tenantId, #[SensitiveParameter] NewEmployer $employer): CesuNumber
    {
        return $this->process(Operation::RegisterEmployer, function () use ($employer): CesuNumber {
            $cesuNumber = CesuNumber::fromString($this->cesuNumbers->next());
            $this->employers[$cesuNumber->value()] = new SimulatedEmployer($cesuNumber, $employer->birthDate());
            $this->registered[] = $cesuNumber;

            return $cesuNumber;
        });
    }

    public function verifyEmployer(TenantId $tenantId, #[SensitiveParameter] EmployerVerification $verification): EmployerStatus
    {
        return $this->process(Operation::VerifyEmployer, function () use ($tenantId, $verification): EmployerStatus {
            $employer = $this->employers[$verification->employer()->cesuNumber()->value()] ?? null;
            if (!$employer instanceof SimulatedEmployer) {
                return new EmployerStatus(false, null, null, false, null, null, false);
            }
            if (!$employer->matches($verification->employer())) {
                throw self::refusal(Operation::VerifyEmployer, self::businessErrors(BusinessError::BirthDateMismatch));
            }

            $employee = $verification->employee()?->cesuNumber();
            $cesuPlus = $employee instanceof CesuNumber && $employer->isCesuPlusActive($employee) ? 'active' : null;

            return new EmployerStatus(
                true,
                $employer->onlineAccountState(),
                $employer->sepaMandateState(),
                $employer->isMandatedBy($tenantId),
                $cesuPlus,
                null,
                $employer->isAiciActive(),
            );
        });
    }

    public function verifyEmployee(TenantId $tenantId, #[SensitiveParameter] EmployeeReference $employee): EmployeeVerificationResult
    {
        return $this->process(Operation::VerifyEmployee, function () use ($employee): EmployeeVerificationResult {
            foreach ($this->employees as [$cesuNumber, $aliases]) {
                if ($this->isKnownEmployee($cesuNumber, $aliases, $employee)) {
                    return EmployeeVerificationResult::of(true, $cesuNumber);
                }
            }

            return EmployeeVerificationResult::of(false, null);
        });
    }

    public function registerMandate(TenantId $tenantId, #[SensitiveParameter] Employer $employer): void
    {
        $this->process(Operation::RegisterMandate, function () use ($tenantId, $employer): null {
            $this->mandateTarget(Operation::RegisterMandate, $tenantId, $employer)->holdBy($tenantId);

            return null;
        });
    }

    public function cancelMandate(TenantId $tenantId, #[SensitiveParameter] Employer $employer): void
    {
        $this->process(Operation::CancelMandate, function () use ($tenantId, $employer): null {
            $this->mandateTarget(Operation::CancelMandate, $tenantId, $employer)->holdBy(null);

            return null;
        });
    }

    public function estimate(TenantId $tenantId, EstimateRequest $request): ContributionsEstimate
    {
        return $this->process(Operation::EstimateContributions, fn(): ContributionsEstimate => $this->estimate);
    }

    public function declare(TenantId $tenantId, #[SensitiveParameter] Declaration $declaration): DeclarationResult
    {
        return $this->process(Operation::DeclareSalary, function () use ($tenantId, $declaration): DeclarationResult {
            $content = $declaration->content();
            $employer = $this->employers[$content->employerCesuNumber()->value()] ?? null;
            if (!$employer instanceof SimulatedEmployer || !$employer->isMandatedBy($tenantId)) {
                throw self::refusal(Operation::DeclareSalary, self::mandateMissing());
            }
            foreach ($this->declarations as $recorded) {
                if ($recorded->duplicates($declaration)) {
                    throw self::refusal(Operation::DeclareSalary, self::businessErrors(BusinessError::DuplicateDeclaration));
                }
            }

            $id = DeclarationId::fromString($this->declarationIds->next());
            $this->declarations[] = new SimulatedDeclaration(
                $id,
                $content->employerCesuNumber(),
                $content->employee()->reference(),
                $content->employmentPeriod(),
            );

            return new DeclarationResult(
                $id,
                $this->declarationResult->contributions(),
                $this->declarationResult->withholdingTax(),
                $this->declarationResult->employerContributionsDebit(),
                $this->declarationResult->employerWithholdingTaxDebit(),
                $this->declarationResult->employerSalaryDebit(),
                $this->declarationResult->grossSalary(),
                $this->declarationResult->netTaxableSalary(),
                $this->declarationResult->salaryTransfer(),
                $this->declarationResult->aiciAmount(),
            );
        });
    }

    public function activateCesuPlus(TenantId $tenantId, CesuPlusActivationRequest $request): CesuPlusActivation
    {
        return $this->process(Operation::ActivateCesuPlus, function () use ($tenantId, $request): CesuPlusActivation {
            $employer = $this->employers[$request->employer()->cesuNumber()->value()] ?? null;
            if (!$employer instanceof SimulatedEmployer || !$employer->matches($request->employer())) {
                throw self::refusal(Operation::ActivateCesuPlus, self::businessErrors(BusinessError::EmployerCesuNumberUnknown));
            }
            if (!$employer->isMandatedBy($tenantId)) {
                throw self::refusal(Operation::ActivateCesuPlus, self::mandateMissing());
            }
            if ($employer->isCesuPlusActive($request->employeeCesuNumber())) {
                throw self::refusal(Operation::ActivateCesuPlus, self::businessErrors(BusinessError::CesuPlusAlreadyActive));
            }

            $employer->activateCesuPlus($request->employeeCesuNumber());

            return CesuPlusActivation::of(true);
        });
    }

    public function activateAici(TenantId $tenantId, #[SensitiveParameter] AiciActivationRequest $request): AiciActivation
    {
        return $this->process(Operation::ActivateAici, function () use ($request): AiciActivation {
            $employer = $this->employers[$request->employer()->cesuNumber()->value()] ?? null;
            if (!$employer instanceof SimulatedEmployer || !$employer->matches($request->employer())) {
                throw self::refusal(Operation::ActivateAici, self::businessErrors(BusinessError::EmployerNotFound));
            }
            if (!$employer->hasCesuPlus()) {
                throw self::refusal(Operation::ActivateAici, self::businessErrors(BusinessError::CesuPlusNotActive));
            }

            $employer->activateAici();

            return AiciActivation::of(true);
        });
    }

    /**
     * The common path of the operations: record the call, throw a scripted failure, then apply a scripted refusal or the
     * built-in rules; a lost answer throws OutcomeUnknown whatever the outcome (the state change of an accepted call is
     * kept, a refusal is swallowed).
     *
     * @template T
     *
     * @param-immediately-invoked-callable $rules
     * @param Closure(): T $rules
     *
     * @throws Throwable
     *
     * @return T
     */
    private function process(Operation $operation, Closure $rules): mixed
    {
        $this->calls[] = $operation;

        $failure = $this->script->nextFailure($operation);
        if ($failure instanceof Throwable) {
            throw $failure;
        }

        $lost = $this->script->takeLostAnswer($operation);
        $refusal = $this->script->nextRefusal($operation);
        if (!$lost) {
            return $this->apply($operation, $refusal, $rules);
        }

        try {
            $this->apply($operation, $refusal, $rules);
            // @mago-expect lint:no-empty-catch-clause The answer is lost: a refusal never reaches the caller, only OutcomeUnknown does.
        } catch (UrssafRequestRejected) {
        }

        throw OutcomeUnknown::fromUnavailable(UrssafUnavailable::withStatus($operation, 504, null, ApiErrors::none()));
    }

    /**
     * A scripted refusal replaces the built-in rules.
     *
     * @template T
     *
     * @param-immediately-invoked-callable $rules
     * @param Closure(): T $rules
     *
     * @throws UrssafRequestRejected
     *
     * @return T
     */
    private function apply(Operation $operation, ?ApiErrors $refusal, Closure $rules): mixed
    {
        if ($refusal instanceof ApiErrors) {
            throw self::refusal($operation, $refusal);
        }

        return $rules();
    }

    /**
     * Methods 030 and 031: the employer of the registry with this birth date, not held by another tenant.
     */
    private function mandateTarget(Operation $operation, TenantId $tenantId, Employer $employer): SimulatedEmployer
    {
        $known = $this->employers[$employer->cesuNumber()->value()] ?? null;
        if (!$known instanceof SimulatedEmployer || !$known->matches($employer)) {
            throw self::refusal($operation, self::accountUnknown());
        }
        if ($known->isHeldByAnotherThan($tenantId)) {
            throw self::refusal($operation, self::mandateHeldElsewhere());
        }

        return $known;
    }

    /**
     * @param array<array-key, EmployeeReference> $aliases
     */
    private function isKnownEmployee(CesuNumber $cesuNumber, array $aliases, EmployeeReference $reference): bool
    {
        if ($reference->cesuNumber()?->equals($cesuNumber) === true) {
            return true;
        }
        foreach ($aliases as $alias) {
            if ($alias->equals($reference)) {
                return true;
            }
        }

        return false;
    }

    private function employer(CesuNumber $cesuNumber): SimulatedEmployer
    {
        return $this->employers[$cesuNumber->value()]
            ?? throw new LogicException(\sprintf('The employer "%s" is not known to the simulator.', $cesuNumber->value()));
    }

    private static function refusal(Operation $operation, ApiErrors $errors): UrssafRequestRejected
    {
        return ErrorClassifier::rejection($operation, 400, $errors);
    }

    private static function businessErrors(BusinessError $error): ApiErrors
    {
        return ApiErrors::of(ApiError::of(ApiErrorCode::Business->value, 'Erreur métier', BusinessErrorCatalogue::text($error)));
    }

    private static function mandateMissing(): ApiErrors
    {
        return ApiErrors::of(ApiError::of(ApiErrorCode::MandateMissing->value, 'Mandat de tierce déclaration absent', null));
    }

    private static function accountUnknown(): ApiErrors
    {
        return ApiErrors::of(ApiError::of(
            ApiErrorCode::AccountUnknown->value,
            'Aucun compte CESU trouvé avec ces paramètres',
            'Aucun compte CESU trouvé avec ces paramètres',
        ));
    }

    private static function mandateHeldElsewhere(): ApiErrors
    {
        return ApiErrors::of(ApiError::of(
            ApiErrorCode::MandateHeldElsewhere->value,
            'Le compte est déjà mandaté',
            'Il existe déjà un autre établissement mandataire pour ce numéro de compte',
        ));
    }
}
