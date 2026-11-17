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

/**
 * Canonical text of a declaration, hashed into its fingerprint (ADR 0006 "same content", checked by a fingerprint that
 * excludes personal data): one `key=value` line per field, absent values written "-", amounts in cents, half-days as
 * integers.
 *
 * It holds the employer Cesu number, the employee reference kind and employee Cesu number when present, the employment
 * kind, the period, the payment date, the type, the nature, the deposit, the income (amounts, hours, supplements, leave
 * periods, foster carer figures) and the approval basis, council and dates. It never holds personal data: NIR, names,
 * birth data, address, e-mail, phone, approval number.
 *
 * @internal
 */
final class CanonicalForm
{
    private const ABSENT = '-';

    public static function fingerprint(DeclarationType $type, DeclarationContent $content): string
    {
        return hash('sha256', self::of($type, $content));
    }

    public static function of(DeclarationType $type, DeclarationContent $content): string
    {
        return implode("\n", [
            ...self::headerLines($type, $content),
            ...self::hourlyIncomeLines($content->hourlyIncome()),
            ...self::fosterCarerIncomeLines($content->fosterCarerIncome()),
            ...self::approvalLines($content->approval()),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function headerLines(DeclarationType $type, DeclarationContent $content): array
    {
        $employee = $content->employee();
        $period = $content->employmentPeriod();
        $paidLeaveIncluded = $employee->paidLeaveIncluded();

        return [
            'employer_cesu_number=' . $content->employerCesuNumber()->value(),
            'type=' . $type->value,
            'start_date=' . $period->start()->toString(),
            'end_date=' . $period->end()->toString(),
            'payment_date=' . $content->paymentDate()->toString(),
            'activity_nature=' . self::text($content->activityNature()?->value),
            'employment_kind=' . $employee->employmentKind()->value,
            'employee_reference_kind=' . $employee->reference()->kind()->value,
            'employee_cesu_number=' . self::text($employee->reference()->cesuNumber()?->value()),
            'paid_leave_included=' . self::text($paidLeaveIncluded === null ? null : (int) $paidLeaveIncluded),
            'deposit=' . self::text($content->deposit()?->amount()->cents()),
        ];
    }

    /**
     * @return list<string>
     */
    private static function hourlyIncomeLines(?HourlyIncome $income): array
    {
        if (!$income instanceof HourlyIncome) {
            return ['hourly_income=' . self::ABSENT];
        }
        $lines = [
            'hourly_wage=' . $income->hourlyWage()->cents(),
            'worked_hours=' . $income->hours()->workedHours(),
            'first_tier_overtime_hours=' . self::text($income->hours()->firstTierOvertimeHours()),
            'second_tier_overtime_hours=' . self::text($income->hours()->secondTierOvertimeHours()),
            'paid_leave_hours=' . self::text($income->paidLeaveHours()),
        ];
        foreach ($income->supplements() as $index => $supplement) {
            $lines[] = \sprintf(
                'supplement[%d]=%s,%s,%d,%s,%s',
                $index,
                $supplement->category()->value,
                $supplement->subCategory()->value,
                $supplement->amount()->cents(),
                self::text($supplement->hourlyWage()?->cents()),
                self::text($supplement->hours()),
            );
        }
        foreach ($income->paidLeavePeriods() as $index => $period) {
            $lines[] = \sprintf(
                'paid_leave_period[%d]=%s,%s,%d',
                $index,
                $period->start()->toString(),
                $period->end()->toString(),
                $period->days()->halfDays(),
            );
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private static function fosterCarerIncomeLines(?FosterCarerIncome $income): array
    {
        if (!$income instanceof FosterCarerIncome) {
            return ['foster_carer_income=' . self::ABSENT];
        }

        return [
            'housing_allowance=' . self::text($income->housingAllowance()?->cents()),
            'maintenance_allowance_half_days=' . self::text($income->maintenanceAllowanceDays()?->halfDays()),
            'particular_constraint_half_days=' . self::text($income->particularConstraintDays()?->halfDays()),
            'full_time_reception_half_days=' . self::text($income->fullTimeReceptionDays()?->halfDays()),
            'part_time_reception_half_days=' . self::text($income->partTimeReceptionDays()?->halfDays()),
            'guaranteed_minimum_per_day=' . self::text($income->guaranteedMinimumPerMaintenanceDay()?->scaled()),
            'smic_per_particular_constraint_day=' . self::text($income->smicPerParticularConstraintDay()?->scaled()),
            'smic_per_full_time_reception_day=' . self::text($income->smicPerFullTimeReceptionDay()?->scaled()),
            'smic_per_part_time_reception_day=' . self::text($income->smicPerPartTimeReceptionDay()?->scaled()),
        ];
    }

    /**
     * @return list<string>
     */
    private static function approvalLines(?FosterCarerApproval $approval): array
    {
        if (!$approval instanceof FosterCarerApproval) {
            return ['approval=' . self::ABSENT];
        }

        return [
            'approval_basis=' . $approval->basis()->value,
            'approval_council=' . self::text($approval->council()?->value()),
            'approval_start_date=' . self::text($approval->startDate()?->toString()),
            'approval_end_date=' . self::text($approval->endDate()?->toString()),
        ];
    }

    private static function text(int|string|null $value): string
    {
        return $value === null ? self::ABSENT : (string) $value;
    }
}
