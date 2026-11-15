<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Query of method 060 (api-reference §2.8): the employer, the Cesu number of the employee and the employee agreement.
 * The wire encoding of `accord` belongs to the infrastructure layer (ADR 0008).
 */
final readonly class CesuPlusActivationRequest
{
    private function __construct(
        private Employer $employer,
        private CesuNumber $employeeCesuNumber,
        private bool $employeeAgreement,
    ) {}

    public static function of(
        Employer $employer,
        CesuNumber $employeeCesuNumber,
        bool $employeeAgreement,
    ): self {
        return new self($employer, $employeeCesuNumber, $employeeAgreement);
    }

    public function employer(): Employer
    {
        return $this->employer;
    }

    public function employeeCesuNumber(): CesuNumber
    {
        return $this->employeeCesuNumber;
    }

    public function employeeAgreement(): bool
    {
        return $this->employeeAgreement;
    }
}
