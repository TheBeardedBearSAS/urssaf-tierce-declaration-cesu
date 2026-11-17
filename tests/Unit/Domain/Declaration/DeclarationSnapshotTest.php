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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationFingerprint;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;

final class DeclarationSnapshotTest extends TestCase
{
    public function testConstructorKeepsEveryPart(): void
    {
        $fingerprint = DeclarationFingerprint::fromString(str_repeat('ab', 32));
        $period = DeclarationSamples::period();
        $snapshot = new DeclarationSnapshot(
            DeclarationSamples::employerCesuNumber(),
            DeclarationType::CesuPlus,
            $period,
            DeclarationSamples::date('2026-10-31'),
            EmploymentKind::FosterCarer,
            EmployeeReferenceKind::Identity,
            null,
            $fingerprint,
        );

        static::assertSame(DeclarationSamples::EMPLOYER_CESU_NUMBER, $snapshot->employerCesuNumber()->value());
        static::assertSame(DeclarationType::CesuPlus, $snapshot->type());
        static::assertSame($period, $snapshot->employmentPeriod());
        static::assertSame('2026-10-31', $snapshot->paymentDate()->toString());
        static::assertSame(EmploymentKind::FosterCarer, $snapshot->employmentKind());
        static::assertSame(EmployeeReferenceKind::Identity, $snapshot->employeeReferenceKind());
        static::assertNull($snapshot->employeeCesuNumber());
        static::assertSame($fingerprint, $snapshot->fingerprint());
    }

    public function testFromADeclarationIdentifiedByCesuNumber(): void
    {
        $declaration = StandardDeclaration::of(DeclarationSamples::content());
        $snapshot = DeclarationSamples::snapshotOf($declaration);

        static::assertSame(DeclarationSamples::EMPLOYER_CESU_NUMBER, $snapshot->employerCesuNumber()->value());
        static::assertSame(DeclarationType::Standard, $snapshot->type());
        static::assertTrue($snapshot->employmentPeriod()->equals(DeclarationSamples::period()));
        static::assertSame('2026-10-31', $snapshot->paymentDate()->toString());
        static::assertSame(EmploymentKind::HomeEmployment, $snapshot->employmentKind());
        static::assertSame(EmployeeReferenceKind::CesuNumber, $snapshot->employeeReferenceKind());
        static::assertSame(DeclarationSamples::EMPLOYEE_CESU_NUMBER, $snapshot->employeeCesuNumber()?->value());
        static::assertSame($declaration->fingerprint(), $snapshot->fingerprint()->value());
    }

    public function testFromADeclarationIdentifiedByNirKeepsNoPersonalData(): void
    {
        $declaration = StandardDeclaration::of(DeclarationSamples::build(null, DeclarationSamples::nirEmployee(), null, null, DeclarationSamples::approval()));
        $snapshot = DeclarationSamples::snapshotOf($declaration);

        static::assertSame(DeclarationType::Standard, $snapshot->type());
        static::assertSame(EmploymentKind::FosterCarer, $snapshot->employmentKind());
        static::assertSame(EmployeeReferenceKind::Nir, $snapshot->employeeReferenceKind());
        static::assertNull($snapshot->employeeCesuNumber());
        static::assertStringNotContainsString('269054958815780', print_r($snapshot, true));
        static::assertStringNotContainsString('Martin', print_r($snapshot, true));
    }

    public function testFromACesuPlusDeclarationKeepsTheType(): void
    {
        static::assertSame(DeclarationType::CesuPlus, DeclarationSamples::snapshotOf(DeclarationSamples::cesuPlus())->type());
    }

    public function testHasSameContentAsComparesTheFingerprints(): void
    {
        $snapshot = DeclarationSamples::snapshot();

        static::assertTrue($snapshot->hasSameContentAs(DeclarationSamples::snapshotOf(DeclarationSamples::standard())));
        static::assertFalse($snapshot->hasSameContentAs(DeclarationSamples::snapshotOf(DeclarationSamples::cesuPlus())));
    }
}
