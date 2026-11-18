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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\AllOf;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;

/**
 * The two rule sets of a declaration (ADR 0009 local pre-checks). Nothing else is checked locally: SMIC, ceilings,
 * deposit, deadlines and eligibility stay server-side.
 */
final class DeclarationRules
{
    /**
     * Rules that depend on the content only, enforced by the declaration constructors.
     *
     * @return Validator<DeclarationContent>
     */
    public static function intrinsic(): Validator
    {
        return AllOf::of(
            new IncomeIsPresent(),
            new HourlyWorkedHours(),
            new PaidLeaveExcludedWhenIncluded(),
            new PaidLeavePeriodsWithinEmployment(),
            new PaidLeavePeriodsDoNotOverlap(),
            new NatureForbiddenForFosterCarer(),
            new ApprovalRequiredForFosterCarer(),
        );
    }

    /**
     * Rules that depend on the context (today's date, the workflow policy).
     *
     * @return Validator<DeclarationContent>
     */
    public static function contextual(CalendarDate $today, bool $allowFosterCarerDeclarations): Validator
    {
        return AllOf::of(
            new EmploymentNotStartedAfterToday($today),
            new FosterCarerDeclarationsAllowed($allowFosterCarerDeclarations),
        );
    }
}
