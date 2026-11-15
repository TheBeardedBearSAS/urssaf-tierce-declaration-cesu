<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use LogicException;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use Throwable;

/**
 * Scripted gateway: one FIFO queue of responses per operation (a value, or a throwable to throw). An unscripted call
 * throws a LogicException. Every call is journaled as "call:<method number>" and recorded.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:too-many-properties
 * @mago-expect lint:cyclomatic-complexity
 */
final class FakeCesuGateway implements CesuGateway
{
    /** @var list<CesuNumber|Throwable> */
    private array $registerEmployer = [];

    /** @var list<EmployerStatus|Throwable> */
    private array $verifyEmployer = [];

    /** @var list<EmployeeVerificationResult|Throwable> */
    private array $verifyEmployee = [];

    /** @var list<Throwable|null> */
    private array $registerMandate = [];

    /** @var list<Throwable|null> */
    private array $cancelMandate = [];

    /** @var list<ContributionsEstimate|Throwable> */
    private array $estimate = [];

    /** @var list<DeclarationResult|Throwable> */
    private array $declare = [];

    /** @var list<CesuPlusActivation|Throwable> */
    private array $activateCesuPlus = [];

    /** @var list<AiciActivation|Throwable> */
    private array $activateAici = [];

    /** @var list<Operation> */
    private array $calls = [];

    /** @var list<string> */
    private array $tenants = [];

    /** @var list<Declaration> */
    private array $declarations = [];

    /** @var list<EmployerVerification> */
    private array $verifications = [];

    /** @var list<Employer> */
    private array $employers = [];

    /** @var list<NewEmployer> */
    private array $newEmployers = [];

    /** @var list<EmployeeReference> */
    private array $employees = [];

    public function __construct(private readonly Journal $journal = new Journal()) {}

    public function willRegisterEmployer(CesuNumber|Throwable $response): void
    {
        $this->registerEmployer[] = $response;
    }

    public function willVerifyEmployer(EmployerStatus|Throwable $response): void
    {
        $this->verifyEmployer[] = $response;
    }

    public function willVerifyEmployee(EmployeeVerificationResult|Throwable $response): void
    {
        $this->verifyEmployee[] = $response;
    }

    /**
     * No argument: the call succeeds; one throwable: the call fails with it.
     */
    public function willRegisterMandate(Throwable ...$failure): void
    {
        $this->registerMandate[] = $failure[0] ?? null;
    }

    /**
     * No argument: the call succeeds; one throwable: the call fails with it.
     */
    public function willCancelMandate(Throwable ...$failure): void
    {
        $this->cancelMandate[] = $failure[0] ?? null;
    }

    public function willEstimate(ContributionsEstimate|Throwable $response): void
    {
        $this->estimate[] = $response;
    }

    public function willDeclare(DeclarationResult|Throwable $response): void
    {
        $this->declare[] = $response;
    }

    public function willActivateCesuPlus(CesuPlusActivation|Throwable $response): void
    {
        $this->activateCesuPlus[] = $response;
    }

    public function willActivateAici(AiciActivation|Throwable $response): void
    {
        $this->activateAici[] = $response;
    }

    public function registerEmployer(TenantId $tenantId, #[SensitiveParameter] NewEmployer $employer): CesuNumber
    {
        $this->called(Operation::RegisterEmployer, $tenantId);
        $this->newEmployers[] = $employer;
        $response = array_shift($this->registerEmployer) ?? throw self::unexpected('registerEmployer');

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function verifyEmployer(TenantId $tenantId, #[SensitiveParameter] EmployerVerification $verification): EmployerStatus
    {
        $this->called(Operation::VerifyEmployer, $tenantId);
        $this->verifications[] = $verification;
        $response = array_shift($this->verifyEmployer) ?? throw self::unexpected('verifyEmployer');

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function verifyEmployee(TenantId $tenantId, #[SensitiveParameter] EmployeeReference $employee): EmployeeVerificationResult
    {
        $this->called(Operation::VerifyEmployee, $tenantId);
        $this->employees[] = $employee;
        $response = array_shift($this->verifyEmployee) ?? throw self::unexpected('verifyEmployee');

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function registerMandate(TenantId $tenantId, #[SensitiveParameter] Employer $employer): void
    {
        $this->called(Operation::RegisterMandate, $tenantId);
        $this->employers[] = $employer;
        if ($this->registerMandate === []) {
            throw self::unexpected('registerMandate');
        }
        $failure = array_shift($this->registerMandate);
        if ($failure instanceof Throwable) {
            throw $failure;
        }
    }

    public function cancelMandate(TenantId $tenantId, #[SensitiveParameter] Employer $employer): void
    {
        $this->called(Operation::CancelMandate, $tenantId);
        $this->employers[] = $employer;
        if ($this->cancelMandate === []) {
            throw self::unexpected('cancelMandate');
        }
        $failure = array_shift($this->cancelMandate);
        if ($failure instanceof Throwable) {
            throw $failure;
        }
    }

    public function estimate(TenantId $tenantId, EstimateRequest $request): ContributionsEstimate
    {
        $this->called(Operation::EstimateContributions, $tenantId);
        $response = array_shift($this->estimate) ?? throw self::unexpected('estimate');

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function declare(TenantId $tenantId, #[SensitiveParameter] Declaration $declaration): DeclarationResult
    {
        $this->called(Operation::DeclareSalary, $tenantId);
        $this->declarations[] = $declaration;
        $response = array_shift($this->declare) ?? throw self::unexpected('declare');

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function activateCesuPlus(TenantId $tenantId, CesuPlusActivationRequest $request): CesuPlusActivation
    {
        $this->called(Operation::ActivateCesuPlus, $tenantId);
        $this->employers[] = $request->employer();
        $response = array_shift($this->activateCesuPlus) ?? throw self::unexpected('activateCesuPlus');

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function activateAici(TenantId $tenantId, #[SensitiveParameter] AiciActivationRequest $request): AiciActivation
    {
        $this->called(Operation::ActivateAici, $tenantId);
        $response = array_shift($this->activateAici) ?? throw self::unexpected('activateAici');

        return $response instanceof Throwable ? throw $response : $response;
    }

    /**
     * @return list<Operation> the operation of each call, in order
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<string> the tenant of each call
     */
    public function tenants(): array
    {
        return $this->tenants;
    }

    /**
     * @return list<Declaration> the declarations sent with 050
     */
    public function declarations(): array
    {
        return $this->declarations;
    }

    /**
     * @return list<EmployerVerification> the verifications sent with 020
     */
    public function verifications(): array
    {
        return $this->verifications;
    }

    /**
     * @return list<Employer> the employers of the calls 030, 031 and 060
     */
    public function employers(): array
    {
        return $this->employers;
    }

    /**
     * @return list<NewEmployer> the employers sent with 010
     */
    public function newEmployers(): array
    {
        return $this->newEmployers;
    }

    /**
     * @return list<EmployeeReference> the employees sent with 021
     */
    public function employees(): array
    {
        return $this->employees;
    }

    private function called(Operation $operation, TenantId $tenantId): void
    {
        $this->journal->add('call:' . $operation->methodNumber());
        $this->calls[] = $operation;
        $this->tenants[] = $tenantId->value();
    }

    private static function unexpected(string $method): LogicException
    {
        return new LogicException('Unexpected ' . $method . '() call.');
    }
}
