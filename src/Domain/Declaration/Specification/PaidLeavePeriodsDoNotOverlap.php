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
 * Paid leave periods do not overlap (api-reference §2.7, ERR_METIER). Periods sharing a day overlap.
 *
 * Violation: "paid_leave_period.overlap" at "hourlyIncome.paidLeavePeriods[j]", one per period j that overlaps an
 * earlier period (parameter "overlapped_index": the first earlier period it overlaps).
 *
 * @implements Validator<DeclarationContent>
 */
final readonly class PaidLeavePeriodsDoNotOverlap implements Validator
{
    /**
     * @param DeclarationContent $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $periods = $subject->hourlyIncome()?->paidLeavePeriods() ?? [];
        $result = ValidationResult::valid();
        foreach ($periods as $later => $period) {
            for ($earlier = 0; $earlier < $later; ++$earlier) {
                if (!$periods[$earlier]->overlaps($period)) {
                    continue;
                }
                $result = $result->with(Violation::error(
                    \sprintf('hourlyIncome.paidLeavePeriods[%d]', $later),
                    'paid_leave_period.overlap',
                    'The paid leave periods must not overlap.',
                    ['overlapped_index' => $earlier],
                ));

                break;
            }
        }

        return $result;
    }
}
