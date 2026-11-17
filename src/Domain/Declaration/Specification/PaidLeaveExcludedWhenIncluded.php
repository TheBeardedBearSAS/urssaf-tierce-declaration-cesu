<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * With the 10 % paid leave included in the hourly wage (`congesPayes = true`), the paid leave hours and periods must
 * not be sent (api-reference §2.7, ERR_METIER).
 *
 * Violations: "hourly_income.paid_leave_hours_forbidden" at "hourlyIncome.paidLeaveHours" and
 * "hourly_income.paid_leave_periods_forbidden" at "hourlyIncome.paidLeavePeriods".
 *
 * @implements Validator<DeclarationContent>
 */
final readonly class PaidLeaveExcludedWhenIncluded implements Validator
{
    /**
     * @param DeclarationContent $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $income = $subject->hourlyIncome();
        if ($income === null || $subject->employee()->paidLeaveIncluded() !== true) {
            return ValidationResult::valid();
        }
        $result = ValidationResult::valid();
        if ($income->paidLeaveHours() !== null) {
            $result = $result->with(Violation::error(
                'hourlyIncome.paidLeaveHours',
                'hourly_income.paid_leave_hours_forbidden',
                'The paid leave hours must not be given when the paid leave is included in the hourly wage.',
            ));
        }
        if ($income->paidLeavePeriods() !== []) {
            $result = $result->with(Violation::error(
                'hourlyIncome.paidLeavePeriods',
                'hourly_income.paid_leave_periods_forbidden',
                'The paid leave periods must not be given when the paid leave is included in the hourly wage.',
            ));
        }

        return $result;
    }
}
