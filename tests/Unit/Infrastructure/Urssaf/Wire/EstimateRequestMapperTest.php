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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Coefficient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementSubCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ExemptionCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\GeographicZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SalaryType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedFosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedHourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EstimateRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\BodyReader;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class EstimateRequestMapperTest extends TestCase
{
    private static function officialExample(): EstimateRequest
    {
        return EstimateRequest::of(
            EmploymentKind::HomeEmployment,
            new SimulatedHourlyIncome(
                Money::fromDecimalString('10'),
                WorkedHours::of(12, 0, 0),
                0,
                [],
                SalaryType::Net,
                ExemptionCode::NoExemption,
                GeographicZone::Metropolitan,
                true,
            ),
            null,
        );
    }

    /**
     * @param list<SalarySupplement> $supplements
     */
    private static function hourly(?int $first, ?int $second, ?int $paidLeaveHours, array $supplements): SimulatedHourlyIncome
    {
        return new SimulatedHourlyIncome(
            Money::fromDecimalString('12.5'),
            WorkedHours::of(100, $first, $second),
            $paidLeaveHours,
            $supplements,
            SalaryType::Gross,
            ExemptionCode::Aeeh,
            GeographicZone::AlsaceMoselle,
            false,
        );
    }

    public function testOfficialExample(): void
    {
        $body = (new EstimateRequestMapper())->toBody(self::officialExample());

        // The key order differs from the Swagger order of the template; the decimals 10 and 10.00 compare equal.
        static::assertEquals(
            Fixtures::decoded('estimate_request'),
            json_decode(JsonEncoder::encode($body), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testExactBodyOfTheOfficialExample(): void
    {
        static::assertSame(
            '{"typeEmploi":"PE","revenusEf":{"salaireHoraire":10.00,"heuresTravaillees":12,"nbHeureSupplementaire1":0,'
            . '"nbHeureSupplementaire2":0,"heuresCp":0,"typeSalaire":"NET","codeExoneration":"02",'
            . '"codeZoneGeographique":"NATIO","congesPayes":true}}',
            JsonEncoder::encode((new EstimateRequestMapper())->toBody(self::officialExample())),
        );
    }

    public function testAbsentOptionalHourlyValuesAreOmitted(): void
    {
        $request = EstimateRequest::of(EmploymentKind::HomeEmployment, self::hourly(null, null, null, []), null);

        static::assertSame(
            '{"typeEmploi":"PE","revenusEf":{"salaireHoraire":12.50,"heuresTravaillees":100,"typeSalaire":"BRUT",'
            . '"codeExoneration":"03","codeZoneGeographique":"ALSMO","congesPayes":false}}',
            JsonEncoder::encode((new EstimateRequestMapper())->toBody($request)),
        );
    }

    public function testSupplementsAndOvertimeAreSent(): void
    {
        $supplement = SalarySupplement::of(
            SupplementCategory::SpecificHours,
            SupplementSubCategory::NightPresence,
            Money::fromDecimalString('20.09'),
            Money::fromDecimalString('12.5'),
            3,
        );
        $request = EstimateRequest::of(EmploymentKind::HomeEmployment, self::hourly(8, 9, 7, [$supplement]), null);

        static::assertSame(
            '{"typeEmploi":"PE","revenusEf":{"salaireHoraire":12.50,"heuresTravaillees":100,'
            . '"nbHeureSupplementaire1":8,"nbHeureSupplementaire2":9,"heuresCp":7,"complementsSalaire":['
            . '{"codeCategorie":"HEUR","codeSousCategorie":"NUIT","montant":20.09,"salaireHoraire":12.50,'
            . '"nombreHeures":3}],"typeSalaire":"BRUT","codeExoneration":"03","codeZoneGeographique":"ALSMO",'
            . '"congesPayes":false}}',
            JsonEncoder::encode((new EstimateRequestMapper())->toBody($request)),
        );
    }

    public function testFosterCarerIncomeEndsWithTheGeographicZone(): void
    {
        $income = new FosterCarerIncome(
            Money::fromDecimalString('100'),
            HalfDays::fromDecimalString('10'),
            null,
            null,
            null,
            Coefficient::fromDecimalString('2.5'),
            null,
            null,
            null,
        );
        $request = EstimateRequest::of(
            EmploymentKind::FosterCarer,
            null,
            SimulatedFosterCarerIncome::of($income, GeographicZone::Overseas),
        );

        static::assertSame(
            '{"typeEmploi":"AF","revenusAf":{"montantIndemniteLogement":100.00,"nbJoursIndemniteEntretien":10,'
            . '"nbMinimumGarantiJourIndemniteEntretien":2.5,"codeZoneGeographique":"97100"}}',
            JsonEncoder::encode((new EstimateRequestMapper())->toBody($request)),
        );
    }

    public function testBothIncomesAreSentInSwaggerOrder(): void
    {
        $request = EstimateRequest::of(
            EmploymentKind::FosterCarer,
            self::hourly(null, null, null, []),
            SimulatedFosterCarerIncome::of(
                new FosterCarerIncome(null, null, null, null, null, null, null, null, null),
                GeographicZone::Metropolitan,
            ),
        );

        static::assertSame(
            ['typeEmploi', 'revenusAf', 'revenusEf'],
            array_keys((new EstimateRequestMapper())->toBody($request)),
        );
        static::assertSame(
            ['codeZoneGeographique' => 'NATIO'],
            BodyReader::object((new EstimateRequestMapper())->toBody($request), 'revenusAf'),
        );
    }
}
