<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee;

/**
 * How an employee is identified (ADR 0004): by Cesu number, by NIR or by administrative data. Stored by a declaration
 * record instead of the NIR or the identity (ADR 0007).
 */
enum EmployeeReferenceKind: string
{
    case CesuNumber = 'cesu_number';
    case Nir = 'nir';
    case Identity = 'identity';
}
