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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Coefficient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Parses a {@see FosterCarerIncomeDraft} into a {@see FosterCarerIncome} with the same bounds, reporting every
 * violation to the collector at the draft property names.
 *
 * @internal
 */
final readonly class FosterCarerIncomeParser
{
    /**
     * Returns the income, or null when there is no draft or after reporting the violations to the collector.
     */
    public function parse(?FosterCarerIncomeDraft $draft, ViolationCollector $collector): ?FosterCarerIncome
    {
        if (!$draft instanceof FosterCarerIncomeDraft) {
            return null;
        }
        $before = $collector->errorCount();
        $housingAllowance = $collector->optional('housingAllowance', $draft->housingAllowance, Money::fromDecimalString(...));
        $maintenance = $this->days($collector, 'maintenanceAllowanceDays', 'maintenance_allowance_days', $draft->maintenanceAllowanceDays);
        $constraints = $this->days($collector, 'particularConstraintDays', 'particular_constraint_days', $draft->particularConstraintDays);
        $fullTime = $this->days($collector, 'fullTimeReceptionDays', 'full_time_reception_days', $draft->fullTimeReceptionDays);
        $partTime = $this->days($collector, 'partTimeReceptionDays', 'part_time_reception_days', $draft->partTimeReceptionDays);
        $guaranteed = $collector->optional(
            'guaranteedMinimumPerMaintenanceDay',
            $draft->guaranteedMinimumPerMaintenanceDay,
            static fn(string $raw): Coefficient => Coefficient::fromDecimalString($raw)->assertBetween(
                Coefficient::fromDecimalString(FosterCarerIncome::MIN_GUARANTEED_MINIMUM),
                Coefficient::fromDecimalString(FosterCarerIncome::MAX_GUARANTEED_MINIMUM),
                'guaranteed_minimum_per_day',
            ),
        );
        $smicConstraints = $collector->optional('smicPerParticularConstraintDay', $draft->smicPerParticularConstraintDay, Coefficient::fromDecimalString(...));
        $smicFullTime = $collector->optional('smicPerFullTimeReceptionDay', $draft->smicPerFullTimeReceptionDay, Coefficient::fromDecimalString(...));
        $smicPartTime = $collector->optional('smicPerPartTimeReceptionDay', $draft->smicPerPartTimeReceptionDay, Coefficient::fromDecimalString(...));
        if ($collector->errorCount() > $before) {
            return null;
        }

        return new FosterCarerIncome(
            $housingAllowance,
            $maintenance,
            $constraints,
            $fullTime,
            $partTime,
            $guaranteed,
            $smicConstraints,
            $smicFullTime,
            $smicPartTime,
        );
    }

    private function days(ViolationCollector $collector, string $path, string $subject, ?string $raw): ?HalfDays
    {
        return $collector->optional(
            $path,
            $raw,
            static fn(string $value): HalfDays => HalfDays::fromDecimalString($value)->assertAtMost(
                HalfDays::fromHalfDays(FosterCarerIncome::MAX_HALF_DAYS),
                $subject,
            ),
        );
    }
}
