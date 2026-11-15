<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Salary supplement category (`codeCategorie`, api-reference §3.8).
 */
enum SupplementCategory: string
{
    case SpecificHours = 'HEUR';
    case BenefitsInKind = 'AVAN';
    case EmployeeContribution = 'PART';
    case Bonus = 'PRIM';
    case Transport = 'TRAN';
    case EndOfContract = 'FINC';

    /**
     * @throws InvalidValue "supplement_category.unknown"
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::unknown('supplement_category');
    }
}
