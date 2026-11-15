<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Coefficient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementSubCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\IncomeEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;

final class IncomeEncodingTest extends TestCase
{
    public function testEveryFosterCarerFieldIsEncodedInSwaggerOrder(): void
    {
        $income = new FosterCarerIncome(
            Money::fromDecimalString('100'),
            HalfDays::fromDecimalString('10'),
            HalfDays::fromDecimalString('2.5'),
            HalfDays::fromDecimalString('3'),
            HalfDays::fromDecimalString('4.5'),
            Coefficient::fromDecimalString('2.5'),
            Coefficient::fromDecimalString('1.5'),
            Coefficient::fromDecimalString('0.75'),
            Coefficient::fromDecimalString('0.25'),
        );

        static::assertSame(
            '{"montantIndemniteLogement":100.00,"nbJoursIndemniteEntretien":10,"nbJoursSujetionsParticulieres":2.5,'
            . '"nbJoursAccueilTempsComplet":3,"nbJoursAccueilTempsPartiel":4.5,'
            . '"nbMinimumGarantiJourIndemniteEntretien":2.5,"nbSmicsJourSujetionsParticulieres":1.5,'
            . '"nbSmicsJourAccueilTempsComplet":0.75,"nbSmicsJourAccueilTempsPartiel":0.25}',
            JsonEncoder::encode(IncomeEncoding::fosterCarer($income)),
        );
    }

    public function testAbsentFosterCarerFieldsAreOmitted(): void
    {
        $none = new FosterCarerIncome(null, null, null, null, null, null, null, null, null);

        static::assertSame([], IncomeEncoding::fosterCarer($none));
    }

    public function testZeroFosterCarerValuesAreKept(): void
    {
        $zero = new FosterCarerIncome(
            Money::fromCents(0),
            HalfDays::fromHalfDays(0),
            null,
            null,
            null,
            null,
            null,
            null,
            Coefficient::fromDecimalString('0'),
        );

        static::assertSame(
            '{"montantIndemniteLogement":0.00,"nbJoursIndemniteEntretien":0,"nbSmicsJourAccueilTempsPartiel":0}',
            JsonEncoder::encode(IncomeEncoding::fosterCarer($zero)),
        );
    }

    public function testASupplementWithoutOptionalPartsOnlyCarriesTheAmount(): void
    {
        $supplement = SalarySupplement::of(
            SupplementCategory::Bonus,
            SupplementSubCategory::Bonus,
            Money::fromDecimalString('50'),
            null,
            null,
        );

        static::assertSame(
            '[{"codeCategorie":"PRIM","codeSousCategorie":"PRIM","montant":50.00}]',
            JsonEncoder::encode(IncomeEncoding::supplements([$supplement])),
        );
    }

    public function testASupplementWithEveryPartIsEncodedInSwaggerOrder(): void
    {
        $supplement = SalarySupplement::of(
            SupplementCategory::SpecificHours,
            SupplementSubCategory::NightPresence,
            Money::fromDecimalString('20.09'),
            Money::fromDecimalString('12.5'),
            0,
        );

        static::assertSame(
            '[{"codeCategorie":"HEUR","codeSousCategorie":"NUIT","montant":20.09,"salaireHoraire":12.50,"nombreHeures":0}]',
            JsonEncoder::encode(IncomeEncoding::supplements([$supplement])),
        );
    }

    public function testSupplementsKeepTheirOrderAndNoneGivesAnEmptyList(): void
    {
        $first = SalarySupplement::of(SupplementCategory::Bonus, SupplementSubCategory::Bonus, Money::fromCents(100), null, null);
        $second = SalarySupplement::of(
            SupplementCategory::SpecificHours,
            SupplementSubCategory::NightPresence,
            Money::fromCents(200),
            null,
            3,
        );

        static::assertSame([], IncomeEncoding::supplements([]));
        static::assertSame(
            '[{"codeCategorie":"PRIM","codeSousCategorie":"PRIM","montant":1.00},{"codeCategorie":"HEUR","codeSousCategorie":"NUIT","montant":2.00,"nombreHeures":3}]',
            JsonEncoder::encode(IncomeEncoding::supplements([$first, $second])),
        );
    }

    public function testTheHourlyPartOmitsEveryAbsentValue(): void
    {
        static::assertSame(
            '{"salaireHoraire":12.50,"heuresTravaillees":100}',
            JsonEncoder::encode(IncomeEncoding::hourly(
                Money::fromDecimalString('12.5'),
                WorkedHours::of(100, null, null),
                null,
                [],
            )),
        );
    }

    public function testTheHourlyPartKeepsZeroValuesAndEveryPart(): void
    {
        $supplement = SalarySupplement::of(SupplementCategory::Bonus, SupplementSubCategory::Bonus, Money::fromCents(100), null, null);

        static::assertSame(
            '{"salaireHoraire":10.00,"heuresTravaillees":12,"nbHeureSupplementaire1":0,"nbHeureSupplementaire2":0,"heuresCp":0,"complementsSalaire":[{"codeCategorie":"PRIM","codeSousCategorie":"PRIM","montant":1.00}]}',
            JsonEncoder::encode(IncomeEncoding::hourly(
                Money::fromCents(1000),
                WorkedHours::of(12, 0, 0),
                0,
                [$supplement],
            )),
        );
    }
}
