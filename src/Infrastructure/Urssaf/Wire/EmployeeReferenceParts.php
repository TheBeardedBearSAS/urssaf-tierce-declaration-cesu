<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * The one branching point of the wire layer on the employee reference sum type (ADR 0004): the three parts that can
 * identify an employee, each null when the variant does not carry it. The encoders of 020, 021 and 050 read them and
 * filter nulls instead of branching again.
 */
final readonly class EmployeeReferenceParts
{
    private function __construct(
        private ?CesuNumber $cesuNumber,
        private ?Nir $nir,
        private ?EmployeeIdentity $identity,
    ) {}

    /**
     * @throws InvariantViolation "wire.unsupported_employee_reference" for a reference of another implementation
     */
    public static function of(EmployeeReference $reference): self
    {
        return match (true) {
            $reference instanceof EmployeeByCesuNumber => new self($reference->cesuNumber(), null, null),
            $reference instanceof EmployeeByNir => new self(null, $reference->nir(), $reference->identity()),
            $reference instanceof EmployeeByIdentity => new self(null, null, $reference->identity()),
            default => throw InvariantViolation::because(
                'wire.unsupported_employee_reference',
                'The employee reference has no wire encoding.',
            ),
        };
    }

    public function cesuNumber(): ?CesuNumber
    {
        return $this->cesuNumber;
    }

    public function nir(): ?Nir
    {
        return $this->nir;
    }

    public function identity(): ?EmployeeIdentity
    {
        return $this->identity;
    }
}
