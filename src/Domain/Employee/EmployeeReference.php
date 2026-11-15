<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * How an employee is identified in a call (ADR 0004 sum type): {@see EmployeeByCesuNumber}, {@see EmployeeByNir} or
 * {@see EmployeeByIdentity}. Consumers branch on {@see self::kind()}.
 */
interface EmployeeReference
{
    public function kind(): EmployeeReferenceKind;

    /**
     * Non-null only for {@see EmployeeByCesuNumber}.
     */
    public function cesuNumber(): ?CesuNumber;

    /**
     * False for another implementation.
     */
    public function equals(self $other): bool;
}
