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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\CesuPlusDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class DeclarationTest extends TestCase
{
    public function testAStandardDeclarationKeepsItsContent(): void
    {
        $content = DeclarationSamples::content();
        $declaration = StandardDeclaration::of($content);

        static::assertSame(DeclarationType::Standard, $declaration->type());
        static::assertSame($content, $declaration->content());
        static::assertNull($declaration->activityNature());
    }

    public function testAStandardDeclarationMayCarryANature(): void
    {
        $declaration = StandardDeclaration::of(DeclarationSamples::build(null, null, null, ActivityNature::Tutoring, null));

        static::assertSame(ActivityNature::Tutoring, $declaration->activityNature());
    }

    public function testAStandardDeclarationWithoutIncomeOnlyWarns(): void
    {
        $declaration = StandardDeclaration::of(DeclarationSamples::contentWithoutIncome());

        static::assertSame(DeclarationType::Standard, $declaration->type());
    }

    public function testAStandardDeclarationRefusesAnIntrinsicViolation(): void
    {
        $content = DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, null, null);

        try {
            StandardDeclaration::of($content);
            static::fail('Expected a validation failure.');
        } catch (ValidationFailed $failed) {
            static::assertSame(['error approval declaration.approval_required'], DomainAssertions::summary($failed->result()));
        }
    }

    public function testACesuPlusDeclarationKeepsItsContentAndNature(): void
    {
        $content = DeclarationSamples::build(null, null, null, ActivityNature::Gardening, null);
        $declaration = CesuPlusDeclaration::of($content);

        static::assertSame(DeclarationType::CesuPlus, $declaration->type());
        static::assertSame($content, $declaration->content());
        static::assertSame(ActivityNature::Gardening, $declaration->activityNature());
    }

    public function testACesuPlusDeclarationRequiresANature(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'activity_nature.required',
            static fn(): CesuPlusDeclaration => CesuPlusDeclaration::of(DeclarationSamples::content()),
        );

        static::assertSame('A Cesu+ declaration requires an activity nature.', $exception->getMessage());
    }

    public function testTheMissingNatureIsReportedBeforeTheIntrinsicRules(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'activity_nature.required',
            static fn(): CesuPlusDeclaration => CesuPlusDeclaration::of(DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, null, null)),
        );
        static::assertNotSame('', $exception->getMessage());
    }

    public function testACesuPlusDeclarationRefusesAnIntrinsicViolation(): void
    {
        $content = DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, ActivityNature::Gardening, null);

        try {
            CesuPlusDeclaration::of($content);
            static::fail('Expected a validation failure.');
        } catch (ValidationFailed $failed) {
            static::assertSame(
                [
                    'error activityNature declaration.nature_forbidden_for_foster_carer',
                    'error approval declaration.approval_required',
                ],
                DomainAssertions::summary($failed->result()),
            );
        }
    }

    public function testAFosterCarerCesuPlusDeclarationIsAlwaysRefused(): void
    {
        $withApproval = DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, ActivityNature::Gardening, DeclarationSamples::approval());

        $this->expectException(ValidationFailed::class);

        CesuPlusDeclaration::of($withApproval);
    }
}
