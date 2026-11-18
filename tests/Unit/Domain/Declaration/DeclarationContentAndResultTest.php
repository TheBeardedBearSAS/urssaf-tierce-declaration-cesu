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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;

final class DeclarationContentAndResultTest extends TestCase
{
    public function testContentKeepsItsNineParts(): void
    {
        $employee = DeclarationSamples::employee(EmploymentKind::HomeEmployment, false);
        $hourly = DeclarationSamples::hourlyIncome();
        $foster = DeclarationSamples::fosterIncome();
        $deposit = DeclarationSamples::deposit();
        $approval = DeclarationSamples::approval();
        $period = DeclarationSamples::period();
        $content = new DeclarationContent(
            DeclarationSamples::employerCesuNumber(),
            $period,
            DeclarationSamples::date('2026-10-31'),
            $employee,
            $hourly,
            $foster,
            $deposit,
            $approval,
            ActivityNature::Childcare,
        );

        static::assertSame(DeclarationSamples::EMPLOYER_CESU_NUMBER, $content->employerCesuNumber()->value());
        static::assertSame($period, $content->employmentPeriod());
        static::assertSame('2026-10-31', $content->paymentDate()->toString());
        static::assertSame($employee, $content->employee());
        static::assertSame($hourly, $content->hourlyIncome());
        static::assertSame($foster, $content->fosterCarerIncome());
        static::assertSame($deposit, $content->deposit());
        static::assertSame($approval, $content->approval());
        static::assertSame(ActivityNature::Childcare, $content->activityNature());
    }

    public function testContentOptionalPartsMayBeAbsent(): void
    {
        $content = DeclarationSamples::contentWithoutIncome();

        static::assertNull($content->hourlyIncome());
        static::assertNull($content->fosterCarerIncome());
        static::assertNull($content->deposit());
        static::assertNull($content->approval());
        static::assertNull($content->activityNature());
    }

    public function testResultKeepsEveryAmountInCents(): void
    {
        $result = DeclarationSamples::result();

        static::assertSame('209299542888', $result->declarationId()->value());
        $contributions = $result->contributions();
        static::assertNotNull($contributions);
        static::assertSame(6310, $contributions->employerContributions()->cents());
        static::assertSame(2009, $contributions->employeeContributions()->cents());
        static::assertSame(500, $result->withholdingTax()?->cents());
        static::assertSame(100, $result->employerContributionsDebit()?->cents());
        static::assertSame(200, $result->employerWithholdingTaxDebit()?->cents());
        static::assertSame(300, $result->employerSalaryDebit()?->cents());
        static::assertSame(40_000, $result->grossSalary()?->cents());
        static::assertSame(30_000, $result->netTaxableSalary()?->cents());
        static::assertNull($result->salaryTransfer());
        static::assertSame(700, $result->aiciAmount()?->cents());
    }

    public function testResultAmountsAreNullable(): void
    {
        $result = new DeclarationResult(DeclarationId::fromString('1'), null, null, null, null, null, null, null, DeclarationSamples::money('9.5'), null);

        static::assertNull($result->contributions());
        static::assertNull($result->withholdingTax());
        static::assertNull($result->employerContributionsDebit());
        static::assertNull($result->employerWithholdingTaxDebit());
        static::assertNull($result->employerSalaryDebit());
        static::assertNull($result->grossSalary());
        static::assertNull($result->netTaxableSalary());
        static::assertSame(950, $result->salaryTransfer()?->cents());
        static::assertNull($result->aiciAmount());
        static::assertInstanceOf(Contributions::class, DeclarationSamples::result()->contributions());
    }
}
