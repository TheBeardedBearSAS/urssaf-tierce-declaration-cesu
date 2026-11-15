<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Salary type of an estimate (`typeSalaire`, api-reference §3.5): gross or net.
 */
enum SalaryType: string
{
    case Gross = 'BRUT';
    case Net = 'NET';

    /**
     * @throws InvalidValue "salary_type.unknown"
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::unknown('salary_type');
    }
}
