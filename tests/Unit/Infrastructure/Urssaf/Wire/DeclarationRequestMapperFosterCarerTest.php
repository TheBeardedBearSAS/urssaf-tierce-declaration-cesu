<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalBasis;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Coefficient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerApproval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\RequestSamples;

final class DeclarationRequestMapperFosterCarerTest extends TestCase
{
    private const PERIOD = '"dateDebut":"2026-10-01","dateFin":"2026-10-31","dateVersement":"2026-10-31"';

    private static function encode(StandardDeclaration $declaration): string
    {
        return JsonEncoder::encode((new DeclarationRequestMapper())->toBody($declaration));
    }

    public function testAnOccasionalReplacementApprovalOnlyCarriesTheIndicators(): void
    {
        $content = DeclarationSamples::build(
            null,
            DeclarationSamples::employee(EmploymentKind::FosterCarer, false),
            null,
            null,
            FosterCarerApproval::of(ApprovalBasis::OccasionalReplacement, null, null, null, null),
        );

        static::assertStringContainsString(
            '"agrementAF":{"indCertificationValiditeAgrement":false,"indRemplacementPonctuel":true}',
            self::encode(StandardDeclaration::of($content)),
        );
    }

    public function testAnEmptyFosterCarerIncomeIsAnEmptyObject(): void
    {
        $content = new DeclarationContent(
            DeclarationSamples::employerCesuNumber(),
            DeclarationSamples::period(),
            DeclarationSamples::date('2026-10-31'),
            DeclarationSamples::employee(EmploymentKind::FosterCarer, null),
            null,
            new FosterCarerIncome(null, null, null, null, null, null, null, null, null),
            null,
            RequestSamples::fullApproval(),
            null,
        );

        static::assertSame(
            '{' . self::PERIOD . ',"typeDeclaration":0,"revenusAf":{},"infosSalarie":{"numeroCesu":"00000123456789",'
            . '"typeEmploi":"AF","agrementAF":{"codeConseilDepartemental":"001","numeroAgrement":"12345678910111213145",'
            . '"dateDebutAgrement":"2019-01-01","dateFinAgrement":"2030-01-30",'
            . '"indCertificationValiditeAgrement":true,"indRemplacementPonctuel":false}}}',
            self::encode(StandardDeclaration::of($content)),
        );
    }

    public function testZeroDepositAndZeroHoursAreKept(): void
    {
        $income = new FosterCarerIncome(Money::fromCents(0), HalfDays::fromHalfDays(0), null, null, null, null, null, Coefficient::fromDecimalString('0'), null);
        $content = new DeclarationContent(
            DeclarationSamples::employerCesuNumber(),
            DeclarationSamples::period(),
            DeclarationSamples::date('2026-10-31'),
            DeclarationSamples::employee(EmploymentKind::FosterCarer, false),
            null,
            $income,
            null,
            RequestSamples::fullApproval(),
            null,
        );

        static::assertStringContainsString(
            '"revenusAf":{"montantIndemniteLogement":0.00,"nbJoursIndemniteEntretien":0,"nbSmicsJourAccueilTempsComplet":0}',
            self::encode(StandardDeclaration::of($content)),
        );
    }

    public function testAFosterCarerIncomeWithTheDepositAndTheApproval(): void
    {
        static::assertSame(
            '{' . self::PERIOD . ',"typeDeclaration":0,"montantAcompte":10.00,"revenusAf":{"montantIndemniteLogement":100.00,'
            . '"nbJoursIndemniteEntretien":10,"nbJoursSujetionsParticulieres":2.5,"nbJoursAccueilTempsComplet":3,'
            . '"nbJoursAccueilTempsPartiel":4,"nbMinimumGarantiJourIndemniteEntretien":2.5,'
            . '"nbSmicsJourSujetionsParticulieres":1.5,"nbSmicsJourAccueilTempsComplet":0.75,'
            . '"nbSmicsJourAccueilTempsPartiel":0.25},"infosSalarie":{"numeroCesu":"00000123456789","typeEmploi":"AF",'
            . '"congesPayes":false,"agrementAF":{"codeConseilDepartemental":"001","dateDebutAgrement":"2019-01-01",'
            . '"dateFinAgrement":"2030-01-30","indCertificationValiditeAgrement":true,'
            . '"indRemplacementPonctuel":false}}}',
            self::encode(StandardDeclaration::of(DeclarationSamples::contentWithFosterIncome())),
        );
    }
}
