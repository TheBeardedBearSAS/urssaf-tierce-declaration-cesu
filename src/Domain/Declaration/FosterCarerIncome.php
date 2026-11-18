<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Income of a foster carer (`RevenusAf`, api-reference §2.7; also used by the estimate, §2.6). Every field is optional.
 * The day counts are at most 99 (multiples of 0.5) and the guaranteed minimum is between 2 and 5; the "31.5 days in
 * total" rule and the SMIC ranges stay server-side. The public constructor is the factory (more than 5 parts).
 */
final readonly class FosterCarerIncome
{
    public const MAX_HALF_DAYS = 198;
    public const MIN_GUARANTEED_MINIMUM = '2';
    public const MAX_GUARANTEED_MINIMUM = '5';

    /**
     * @throws InvalidValue "maintenance_allowance_days.out_of_range", "particular_constraint_days.out_of_range",
     *                      "full_time_reception_days.out_of_range", "part_time_reception_days.out_of_range" or
     *                      "guaranteed_minimum_per_day.out_of_range"
     */
    public function __construct(
        private ?Money $housingAllowance,
        private ?HalfDays $maintenanceAllowanceDays,
        private ?HalfDays $particularConstraintDays,
        private ?HalfDays $fullTimeReceptionDays,
        private ?HalfDays $partTimeReceptionDays,
        private ?Coefficient $guaranteedMinimumPerMaintenanceDay,
        private ?Coefficient $smicPerParticularConstraintDay,
        private ?Coefficient $smicPerFullTimeReceptionDay,
        private ?Coefficient $smicPerPartTimeReceptionDay,
    ) {
        $maxDays = HalfDays::fromHalfDays(self::MAX_HALF_DAYS);
        $maintenanceAllowanceDays?->assertAtMost($maxDays, 'maintenance_allowance_days');
        $particularConstraintDays?->assertAtMost($maxDays, 'particular_constraint_days');
        $fullTimeReceptionDays?->assertAtMost($maxDays, 'full_time_reception_days');
        $partTimeReceptionDays?->assertAtMost($maxDays, 'part_time_reception_days');
        $guaranteedMinimumPerMaintenanceDay?->assertBetween(
            Coefficient::fromDecimalString(self::MIN_GUARANTEED_MINIMUM),
            Coefficient::fromDecimalString(self::MAX_GUARANTEED_MINIMUM),
            'guaranteed_minimum_per_day',
        );
    }

    public function housingAllowance(): ?Money
    {
        return $this->housingAllowance;
    }

    public function maintenanceAllowanceDays(): ?HalfDays
    {
        return $this->maintenanceAllowanceDays;
    }

    public function particularConstraintDays(): ?HalfDays
    {
        return $this->particularConstraintDays;
    }

    public function fullTimeReceptionDays(): ?HalfDays
    {
        return $this->fullTimeReceptionDays;
    }

    public function partTimeReceptionDays(): ?HalfDays
    {
        return $this->partTimeReceptionDays;
    }

    public function guaranteedMinimumPerMaintenanceDay(): ?Coefficient
    {
        return $this->guaranteedMinimumPerMaintenanceDay;
    }

    public function smicPerParticularConstraintDay(): ?Coefficient
    {
        return $this->smicPerParticularConstraintDay;
    }

    public function smicPerFullTimeReceptionDay(): ?Coefficient
    {
        return $this->smicPerFullTimeReceptionDay;
    }

    public function smicPerPartTimeReceptionDay(): ?Coefficient
    {
        return $this->smicPerPartTimeReceptionDay;
    }
}
