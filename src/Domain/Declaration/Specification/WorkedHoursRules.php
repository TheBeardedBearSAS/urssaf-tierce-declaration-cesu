<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * Overtime thresholds (api-reference §2.6 and §2.7, ADR 0009), shared by the declaration and the estimate; paths are
 * relative to the income.
 * - overtime (+25 % or +50 %) requires at least 40 normal hours: "worked_hours.overtime_requires_forty_normal_hours"
 *   at "workedHours";
 * - +50 % overtime requires at least 8 hours at +25 %: "worked_hours.second_tier_requires_eight_first_tier_hours" at
 *   "secondTierOvertimeHours".
 *
 * @implements Validator<WorkedHours>
 */
final readonly class WorkedHoursRules implements Validator
{
    public const MIN_WORKED_HOURS = 40;
    public const MIN_FIRST_TIER_OVERTIME_HOURS = 8;

    /**
     * @param WorkedHours $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $first = $subject->firstTierOvertimeHoursOrZero();
        $second = $subject->secondTierOvertimeHoursOrZero();
        $result = ValidationResult::valid();
        if (($first > 0 || $second > 0) && $subject->workedHours() < self::MIN_WORKED_HOURS) {
            $result = $result->with(Violation::error(
                'workedHours',
                'worked_hours.overtime_requires_forty_normal_hours',
                'Overtime requires at least 40 normal hours.',
                ['min_worked_hours' => self::MIN_WORKED_HOURS],
            ));
        }
        if ($second > 0 && $first < self::MIN_FIRST_TIER_OVERTIME_HOURS) {
            $result = $result->with(Violation::error(
                'secondTierOvertimeHours',
                'worked_hours.second_tier_requires_eight_first_tier_hours',
                'Second-tier overtime requires at least 8 first-tier overtime hours.',
                ['min_first_tier_overtime_hours' => self::MIN_FIRST_TIER_OVERTIME_HOURS],
            ));
        }

        return $result;
    }
}
