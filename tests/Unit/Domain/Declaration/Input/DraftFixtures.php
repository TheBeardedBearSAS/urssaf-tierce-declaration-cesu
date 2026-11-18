<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationContext;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\EmploymentPeriodDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\FosterCarerApprovalDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\FosterCarerIncomeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\HourlyIncomeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\PaidLeavePeriodDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\SalarySupplementDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\DeclaredEmployeeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;

/**
 * Hand-written drafts: a complete valid one and the pieces to break it.
 */
final class DraftFixtures
{
    public static function context(bool $allowFosterCarer = false): DeclarationContext
    {
        return DeclarationContext::of(DeclarationSamples::today(), $allowFosterCarer);
    }

    public static function employee(string $kind = 'PE', ?bool $paidLeaveIncluded = false): DeclaredEmployeeDraft
    {
        return new DeclaredEmployeeDraft(
            DeclarationSamples::EMPLOYEE_CESU_NUMBER,
            null,
            null,
            null,
            null,
            null,
            null,
            $kind,
            $paidLeaveIncluded,
        );
    }

    public static function hourlyIncome(): HourlyIncomeDraft
    {
        return new HourlyIncomeDraft(
            '12.5',
            100,
            8,
            9,
            7,
            [new SalarySupplementDraft('HEUR', 'NUIT', '20.09', '12.5', 3), new SalarySupplementDraft('PRIM', 'PRIM', '50', null, null)],
            [new PaidLeavePeriodDraft('2026-10-05', '2026-10-06', '1.5'), new PaidLeavePeriodDraft('2026-10-20', '2026-10-21', '2')],
        );
    }

    public static function fosterIncome(): FosterCarerIncomeDraft
    {
        return new FosterCarerIncomeDraft('100', '10', '2.5', '3', '4', '2.5', '1.5', '0.75', '0.25');
    }

    public static function approval(): FosterCarerApprovalDraft
    {
        return new FosterCarerApprovalDraft('001', '12345678910111213145', '2019-01-01', '2030-01-30', true, false);
    }

    public static function valid(): DeclarationDraft
    {
        return new DeclarationDraft(
            DeclarationSamples::EMPLOYER_CESU_NUMBER,
            0,
            new EmploymentPeriodDraft('2026-10-01', '2026-10-31'),
            '2026-10-31',
            null,
            '10',
            self::hourlyIncome(),
            null,
            self::employee(),
            null,
        );
    }

    public static function empty(): DeclarationDraft
    {
        return new DeclarationDraft(null, null, null, null, null, null, null, null, null, null);
    }

    /**
     * The valid draft with some parts replaced.
     */
    public static function withParts(
        ?EmploymentPeriodDraft $period,
        ?HourlyIncomeDraft $hourlyIncome,
        ?DeclaredEmployeeDraft $employee,
        ?FosterCarerIncomeDraft $fosterCarerIncome,
        ?FosterCarerApprovalDraft $approval,
    ): DeclarationDraft {
        $valid = self::valid();

        return new DeclarationDraft(
            $valid->employerCesuNumber,
            $valid->type,
            $period ?? $valid->employmentPeriod,
            $valid->paymentDate,
            $valid->activityNature,
            $valid->deposit,
            $hourlyIncome ?? $valid->hourlyIncome,
            $fosterCarerIncome,
            $employee ?? $valid->employee,
            $approval,
        );
    }

    /**
     * The valid draft with its scalar fields replaced (a null argument keeps the valid value, '' means missing).
     */
    public static function withScalars(
        ?string $employerCesuNumber,
        ?int $type,
        ?string $paymentDate,
        ?string $activityNature,
        ?string $deposit,
    ): DeclarationDraft {
        $valid = self::valid();

        return new DeclarationDraft(
            $employerCesuNumber ?? $valid->employerCesuNumber,
            $type ?? $valid->type,
            $valid->employmentPeriod,
            $paymentDate ?? $valid->paymentDate,
            $activityNature ?? $valid->activityNature,
            $deposit ?? $valid->deposit,
            $valid->hourlyIncome,
            $valid->fosterCarerIncome,
            $valid->employee,
            $valid->approval,
        );
    }
}
