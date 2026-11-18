<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Specification;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ActivityNature;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\DeclarationRules;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class DeclarationRulesTest extends TestCase
{
    public function testIntrinsicRulesRunInOrder(): void
    {
        $content = new DeclarationContent(
            DeclarationSamples::employerCesuNumber(),
            DeclarationSamples::period(),
            DeclarationSamples::today(),
            DeclarationSamples::employee(EmploymentKind::FosterCarer, false),
            null,
            null,
            null,
            null,
            ActivityNature::Gardening,
        );

        static::assertSame(
            [
                'warning  declaration.income_missing',
                'error activityNature declaration.nature_forbidden_for_foster_carer',
                'error approval declaration.approval_required',
            ],
            DomainAssertions::summary(DeclarationRules::intrinsic()->validate($content)),
        );
    }

    public function testIntrinsicRulesAcceptAValidContent(): void
    {
        static::assertSame([], DomainAssertions::summary(DeclarationRules::intrinsic()->validate(DeclarationSamples::content())));
    }

    public function testContextualRulesRunInOrder(): void
    {
        $content = DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), DeclarationSamples::period('2026-11-20', '2026-11-30'), null, null);

        static::assertSame(
            [
                'error employmentPeriod.startDate employment_period.starts_after_today',
                'error employee.employmentKind declaration.foster_carer_unsupported',
            ],
            DomainAssertions::summary(DeclarationRules::contextual(DeclarationSamples::today(), false)->validate($content)),
        );
    }

    public function testContextualRulesUseTheGivenParameters(): void
    {
        $content = DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, null, null);

        static::assertSame([], DomainAssertions::summary(DeclarationRules::contextual(DeclarationSamples::today(), true)->validate($content)));
        static::assertSame(
            ['error employmentPeriod.startDate employment_period.starts_after_today'],
            DomainAssertions::summary(DeclarationRules::contextual(DeclarationSamples::date('2026-09-30'), true)->validate($content)),
        );
    }
}
