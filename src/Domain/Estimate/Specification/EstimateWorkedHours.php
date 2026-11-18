<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\WorkedHoursRules;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;

/**
 * Applies {@see WorkedHoursRules} to the hours of the simulated hourly income, nested under "hourlyIncome".
 *
 * @implements Validator<EstimateRequest>
 */
final readonly class EstimateWorkedHours implements Validator
{
    /**
     * @param EstimateRequest $subject
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
