<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

/**
 * Raw hourly income (`RevenusEf`). The hourly wage is a decimal string, the hours are integers.
 */
final readonly class HourlyIncomeDraft
{
    /**
     * @param list<SalarySupplementDraft> $supplements
     * @param list<PaidLeavePeriodDraft>  $paidLeavePeriods
     */
    public function __construct(
        public ?string $hourlyWage,
        public ?int $workedHours,
        public ?int $firstTierOvertimeHours,
        public ?int $secondTierOvertimeHours,
        public ?int $paidLeaveHours,
        public array $supplements = [],
        public array $paidLeavePeriods = [],
    ) {}
}
