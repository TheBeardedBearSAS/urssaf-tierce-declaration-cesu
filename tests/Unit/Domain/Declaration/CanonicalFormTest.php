<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ActivityNature;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalBasis;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\CanonicalForm;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerApproval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;

final class CanonicalFormTest extends TestCase
{
    private const FULL = <<<'TEXT'
        employer_cesu_number=Z0003752390009
        type=0
        start_date=2026-10-01
        end_date=2026-10-31
        payment_date=2026-10-31
        activity_nature=08
        employment_kind=AF
        employee_reference_kind=nir
        employee_cesu_number=-
        paid_leave_included=1
        deposit=1000
        hourly_wage=1250
        worked_hours=100
        first_tier_overtime_hours=4
        second_tier_overtime_hours=9
        paid_leave_hours=7
        supplement[0]=HEUR,NUIT,2009,1250,3
        supplement[1]=PRIM,PRIM,5000,-,-
        paid_leave_period[0]=2026-10-05,2026-10-06,3
        paid_leave_period[1]=2026-10-20,2026-10-21,4
        housing_allowance=10000
        maintenance_allowance_half_days=20
        particular_constraint_half_days=5
        full_time_reception_half_days=6
        part_time_reception_half_days=8
        guaranteed_minimum_per_day=25000
        smic_per_particular_constraint_day=15000
        smic_per_full_time_reception_day=7500
        smic_per_part_time_reception_day=2500
        approval_basis=certified_approval
        approval_council=001
        approval_start_date=2019-01-01
        approval_end_date=2030-01-30
        TEXT;

    private const MINIMAL = <<<'TEXT'
        employer_cesu_number=Z0003752390009
        type=1
        start_date=2026-10-01
        end_date=2026-10-31
        payment_date=2026-10-31
        activity_nature=-
        employment_kind=PE
        employee_reference_kind=cesu_number
        employee_cesu_number=00000123456789
        paid_leave_included=0
        deposit=-
        hourly_income=-
        foster_carer_income=-
        approval=-
        TEXT;

    public function testFullCanonicalForm(): void
    {
        static::assertSame(self::FULL, CanonicalForm::of(DeclarationType::Standard, DeclarationSamples::fullContent()));
    }

    public function testMinimalCanonicalForm(): void
    {
        static::assertSame(self::MINIMAL, CanonicalForm::of(DeclarationType::CesuPlus, DeclarationSamples::contentWithoutIncome()));
    }

    public function testFingerprintsAreFrozen(): void
    {
        static::assertSame(
            '9c6476e8f2f94e8d6b6faf7ae2701b9cc2126a29067d08eae4a4c20c10d2e296',
            CanonicalForm::fingerprint(DeclarationType::Standard, DeclarationSamples::fullContent()),
        );
        static::assertSame(
            '6a846458bac9f5eae2956ef12db8a9c9a5951764411374b4fe22008779bb37cb',
            CanonicalForm::fingerprint(DeclarationType::CesuPlus, DeclarationSamples::contentWithoutIncome()),
        );
        static::assertSame(hash('sha256', self::MINIMAL), CanonicalForm::fingerprint(DeclarationType::CesuPlus, DeclarationSamples::contentWithoutIncome()));
    }

    public function testNoPersonalDataIsInTheCanonicalForm(): void
    {
        $form = CanonicalForm::of(DeclarationType::Standard, DeclarationSamples::fullContent());

        foreach (['269054958815780', 'Martin', 'Durand', 'Camille', 'Lyon', '1980-02-03', '069', 'Exemple', '75001', 'example.org', '0102030405'] as $personalData) {
            static::assertStringNotContainsString($personalData, $form);
        }
    }

    public function testAbsentOptionalValuesAreWrittenAsDashes(): void
    {
        $content = new DeclarationContent(
            DeclarationSamples::employerCesuNumber(),
            DeclarationSamples::period(),
            DeclarationSamples::date('2026-10-31'),
            DeclarationSamples::employee(EmploymentKind::HomeEmployment, null),
            null,
            new FosterCarerIncome(null, null, null, null, null, null, null, null, null),
            null,
            FosterCarerApproval::of(ApprovalBasis::OccasionalReplacement, null, null, null, null),
            null,
        );

        static::assertSame(
            <<<'TEXT'
                employer_cesu_number=Z0003752390009
                type=0
                start_date=2026-10-01
                end_date=2026-10-31
                payment_date=2026-10-31
                activity_nature=-
                employment_kind=PE
                employee_reference_kind=cesu_number
                employee_cesu_number=00000123456789
                paid_leave_included=-
                deposit=-
                hourly_income=-
                housing_allowance=-
                maintenance_allowance_half_days=-
                particular_constraint_half_days=-
                full_time_reception_half_days=-
                part_time_reception_half_days=-
                guaranteed_minimum_per_day=-
                smic_per_particular_constraint_day=-
                smic_per_full_time_reception_day=-
                smic_per_part_time_reception_day=-
                approval_basis=occasional_replacement
                approval_council=-
                approval_start_date=-
                approval_end_date=-
                TEXT,
            CanonicalForm::of(DeclarationType::Standard, $content),
        );
    }

    public function testHourlyIncomeWithoutOptionalValues(): void
    {
        $form = CanonicalForm::of(DeclarationType::Standard, DeclarationSamples::content());

        static::assertStringContainsString("\nhourly_wage=1250\nworked_hours=100\nfirst_tier_overtime_hours=4\nsecond_tier_overtime_hours=-\npaid_leave_hours=-\nfoster_carer_income=-\napproval=-", $form);
    }

    public function testDeclarationsExposeTheirFingerprint(): void
    {
        $standard = DeclarationSamples::standard();

        static::assertSame(CanonicalForm::fingerprint(DeclarationType::Standard, $standard->content()), $standard->fingerprint());
        static::assertSame(64, \strlen($standard->fingerprint()));
        static::assertNotSame($standard->fingerprint(), DeclarationSamples::cesuPlus()->fingerprint());
        static::assertSame(
            CanonicalForm::fingerprint(DeclarationType::CesuPlus, DeclarationSamples::cesuPlus()->content()),
            DeclarationSamples::cesuPlus()->fingerprint(),
        );
    }

    public function testEveryDeclaredFieldChangesTheFingerprint(): void
    {
        $base = DeclarationSamples::standard()->fingerprint();

        $variants = [
            'period' => DeclarationSamples::build(null, null, DeclarationSamples::period('2026-10-02', '2026-10-31'), null, null),
            'end of the period' => DeclarationSamples::build(null, null, DeclarationSamples::period('2026-10-01', '2026-10-30'), null, null),
            'nature' => DeclarationSamples::build(null, null, null, ActivityNature::Gardening, null),
            'income' => DeclarationSamples::build(DeclarationSamples::hourlyIncomeWithLeaveHours(3), null, null, null, null),
            'employment kind' => DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, null, null),
            'paid leave flag' => DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::HomeEmployment, true), null, null, null),
        ];

        foreach ($variants as $name => $content) {
            static::assertNotSame($base, CanonicalForm::fingerprint(DeclarationType::Standard, $content), $name);
        }
        static::assertSame($base, StandardDeclaration::of(DeclarationSamples::content())->fingerprint());
    }
}
