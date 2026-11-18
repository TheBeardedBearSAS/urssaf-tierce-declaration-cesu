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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\HourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\PaidLeavePeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementSubCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Parses a {@see HourlyIncomeDraft} into a {@see HourlyIncome} with the same bounds, reporting every violation to the
 * collector at the draft property names ("hourlyWage", "supplements[1].amount", "paidLeavePeriods[0].endDate").
 *
 * @internal
 */
final readonly class HourlyIncomeParser
{
    /**
     * Returns the income, or null when there is no draft or after reporting the violations to the collector.
     */
    public function parse(?HourlyIncomeDraft $draft, ViolationCollector $collector): ?HourlyIncome
    {
        if (!$draft instanceof HourlyIncomeDraft) {
            return null;
        }
        $before = $collector->errorCount();
        $hourlyWage = $collector->required('hourlyWage', 'hourly_wage', $draft->hourlyWage, self::hourlyWage(...));
        $workedHours = $collector->requiredInteger('workedHours', 'worked_hours', $draft->workedHours, 0, WorkedHours::MAX_WORKED_HOURS);
        $firstTier = $collector->optionalInteger(
            'firstTierOvertimeHours',
            'first_tier_overtime_hours',
            $draft->firstTierOvertimeHours,
            0,
            WorkedHours::MAX_FIRST_TIER_OVERTIME_HOURS,
        );
        $secondTier = $collector->optionalInteger(
            'secondTierOvertimeHours',
            'second_tier_overtime_hours',
            $draft->secondTierOvertimeHours,
            0,
            WorkedHours::MAX_SECOND_TIER_OVERTIME_HOURS,
        );
        $paidLeaveHours = $collector->optionalInteger('paidLeaveHours', 'paid_leave_hours', $draft->paidLeaveHours, 0, HourlyIncome::MAX_PAID_LEAVE_HOURS);
        $supplements = $this->parseSupplements($draft->supplements, $collector);
        $paidLeavePeriods = $this->parsePaidLeavePeriods($draft->paidLeavePeriods, $collector);
        if ($collector->errorCount() > $before) {
            return null;
        }
        // The required parts reported a violation when missing, so neither is null from here.
        /** @var Money $hourlyWage */
        /** @var int $workedHours */

        return HourlyIncome::of(
            $hourlyWage,
            WorkedHours::of($workedHours, $firstTier, $secondTier),
            $paidLeaveHours,
            $supplements,
            $paidLeavePeriods,
        );
    }

    /**
     * @param list<SalarySupplementDraft> $drafts
     *
     * @return list<SalarySupplement> the valid supplements (the invalid ones are reported)
     */
    private function parseSupplements(array $drafts, ViolationCollector $collector): array
    {
        $supplements = [];
        foreach ($drafts as $index => $draft) {
            $supplementCollector = new ViolationCollector();
            $supplement = $this->parseSupplement($draft, $supplementCollector);
            $collector->merge($supplementCollector->result(), \sprintf('supplements[%d]', $index));
            if ($supplement instanceof SalarySupplement) {
                $supplements[] = $supplement;
            }
        }

        return $supplements;
    }

    private function parseSupplement(SalarySupplementDraft $draft, ViolationCollector $collector): ?SalarySupplement
    {
        $category = $collector->required('category', 'supplement_category', $draft->category, SupplementCategory::fromCode(...));
        $subCategory = $collector->required('subCategory', 'supplement_sub_category', $draft->subCategory, SupplementSubCategory::fromCode(...));
        $amount = $collector->required(
            'amount',
            'supplement_amount',
            $draft->amount,
            static fn(string $raw): Money => Money::fromDecimalString($raw)->assertAtMost(
                Money::fromCents(SalarySupplement::MAX_AMOUNT_CENTS),
                'supplement_amount',
            ),
        );
        $hourlyWage = $collector->optional('hourlyWage', $draft->hourlyWage, self::hourlyWage(...));
        $hours = $collector->optionalInteger('hours', 'supplement_hours', $draft->hours, 0, SalarySupplement::MAX_HOURS);
        if ($collector->errorCount() > 0) {
            return null;
        }
        // The required parts reported a violation when missing, so none of them is null from here.
        /** @var SupplementCategory $category */
        /** @var SupplementSubCategory $subCategory */
        /** @var Money $amount */

        return $collector->attempt('subCategory', static fn(): SalarySupplement => SalarySupplement::of($category, $subCategory, $amount, $hourlyWage, $hours));
    }

    /**
     * @param list<PaidLeavePeriodDraft> $drafts
     *
     * @return list<PaidLeavePeriod> the valid periods (the invalid ones are reported)
     */
    private function parsePaidLeavePeriods(array $drafts, ViolationCollector $collector): array
    {
        $periods = [];
        foreach ($drafts as $index => $draft) {
            $periodCollector = new ViolationCollector();
            $period = $this->parsePaidLeavePeriod($draft, $periodCollector);
            $collector->merge($periodCollector->result(), \sprintf('paidLeavePeriods[%d]', $index));
            if ($period instanceof PaidLeavePeriod) {
                $periods[] = $period;
            }
        }

        return $periods;
    }

    private function parsePaidLeavePeriod(PaidLeavePeriodDraft $draft, ViolationCollector $collector): ?PaidLeavePeriod
    {
        $start = $collector->required('startDate', 'start_date', $draft->startDate, CalendarDate::fromString(...));
        $end = $collector->required('endDate', 'end_date', $draft->endDate, CalendarDate::fromString(...));
        $days = $collector->required(
            'days',
            'paid_leave_days',
            $draft->days,
            static fn(string $raw): HalfDays => HalfDays::fromDecimalString($raw)->assertAtMost(
                HalfDays::fromHalfDays(PaidLeavePeriod::MAX_HALF_DAYS),
                'paid_leave_days',
            ),
        );
        if ($start === null || $end === null || $days === null) {
            return null;
        }

        return $collector->attempt('endDate', static fn(): PaidLeavePeriod => PaidLeavePeriod::of($start, $end, $days));
    }

    private static function hourlyWage(string $raw): Money
    {
        return Money::fromDecimalString($raw)->assertAtMost(Money::fromCents(HourlyIncome::MAX_HOURLY_WAGE_CENTS), 'hourly_wage');
    }
}
