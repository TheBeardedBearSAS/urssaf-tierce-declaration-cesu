<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * The employment period must not start after today (api-reference §2.7, ADR 0009). Starting today is accepted; the end
 * date is not checked. "Today" is computed by the caller in the business time zone (ADR 0001).
 *
 * Violation: "employment_period.starts_after_today" at "employmentPeriod.startDate".
 *
 * @implements Validator<DeclarationContent>
 */
final readonly class EmploymentNotStartedAfterToday implements Validator
{
    public function __construct(private CalendarDate $today) {}

    /**
     * @param DeclarationContent $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if (!$subject->employmentPeriod()->startsAfter($this->today)) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'employmentPeriod.startDate',
            'employment_period.starts_after_today',
            'The employment period must not start after today.',
        ));
    }
}
