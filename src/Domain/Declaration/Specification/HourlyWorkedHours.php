<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;

/**
 * Applies {@see WorkedHoursRules} to the hours of the hourly income, nested under "hourlyIncome".
 *
 * @implements Validator<DeclarationContent>
 */
final readonly class HourlyWorkedHours implements Validator
{
    /**
     * @param DeclarationContent $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $income = $subject->hourlyIncome();
        if ($income === null) {
            return ValidationResult::valid();
        }

        return (new WorkedHoursRules())->validate($income->hours())->withPathPrefix('hourlyIncome');
    }
}
