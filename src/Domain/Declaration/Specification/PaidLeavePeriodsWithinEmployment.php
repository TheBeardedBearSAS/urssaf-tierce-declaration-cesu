<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * Every paid leave period lies inside the employment period (api-reference §2.7, ERR_METIER).
 *
 * Violation: "paid_leave_period.outside_employment_period" at "hourlyIncome.paidLeavePeriods[i]".
 *
 * @implements Validator<DeclarationContent>
 */
final readonly class PaidLeavePeriodsWithinEmployment implements Validator
{
    /**
     * @param DeclarationContent $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $periods = $subject->hourlyIncome()?->paidLeavePeriods() ?? [];
        $result = ValidationResult::valid();
        foreach ($periods as $index => $period) {
            if ($period->isWithin($subject->employmentPeriod())) {
                continue;
            }
            $result = $result->with(Violation::error(
                \sprintf('hourlyIncome.paidLeavePeriods[%d]', $index),
                'paid_leave_period.outside_employment_period',
                'The paid leave period must be inside the employment period.',
            ));
        }

        return $result;
    }
}
