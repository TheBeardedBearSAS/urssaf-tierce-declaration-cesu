<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Coefficient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Income objects shared by the estimate (040) and the declaration (050), api-reference §2.6 and §2.7. Absent optional
 * values are omitted, never sent as null; amounts and quantities are exact decimal literals.
 */
final class IncomeEncoding
{
    /**
     * `RevenusAf`: every field is optional, so the result is empty when no field is set.
     *
     * @return array<string, JsonDecimal>
     */
    public static function fosterCarer(FosterCarerIncome $income): array
    {
        return array_filter(
            [
                'montantIndemniteLogement' => self::money($income->housingAllowance()),
                'nbJoursIndemniteEntretien' => self::days($income->maintenanceAllowanceDays()),
                'nbJoursSujetionsParticulieres' => self::days($income->particularConstraintDays()),
                'nbJoursAccueilTempsComplet' => self::days($income->fullTimeReceptionDays()),
                'nbJoursAccueilTempsPartiel' => self::days($income->partTimeReceptionDays()),
                'nbMinimumGarantiJourIndemniteEntretien' => self::coefficient(
                    $income->guaranteedMinimumPerMaintenanceDay(),
                ),
                'nbSmicsJourSujetionsParticulieres' => self::coefficient($income->smicPerParticularConstraintDay()),
                'nbSmicsJourAccueilTempsComplet' => self::coefficient($income->smicPerFullTimeReceptionDay()),
                'nbSmicsJourAccueilTempsPartiel' => self::coefficient($income->smicPerPartTimeReceptionDay()),
            ],
            static fn(?JsonDecimal $value): bool => $value instanceof JsonDecimal,
        );
    }

    /**
     * The hourly part shared by `RevenusEf` (050) and `RevenusEfSimu` (040), up to `complementsSalaire` (omitted when
     * there is no supplement).
     *
     * @param list<SalarySupplement> $supplements
     *
     * @return array<string, mixed>
     */
    public static function hourly(Money $hourlyWage, WorkedHours $hours, ?int $paidLeaveHours, array $supplements): array
    {
        return array_filter(
            [
                'salaireHoraire' => MoneyEncoding::encode($hourlyWage),
                'heuresTravaillees' => $hours->workedHours(),
                'nbHeureSupplementaire1' => $hours->firstTierOvertimeHours(),
                'nbHeureSupplementaire2' => $hours->secondTierOvertimeHours(),
                'heuresCp' => $paidLeaveHours,
                'complementsSalaire' => $supplements === [] ? null : self::supplements($supplements),
            ],
            static fn(mixed $value): bool => $value !== null,
        );
    }

    /**
     * `ComplementSalaire`: the amount is always sent, the hourly wage and the hours only when set.
     *
     * @param list<SalarySupplement> $supplements
     *
     * @return list<array<string, mixed>>
     */
    public static function supplements(array $supplements): array
    {
        return array_map(
            static fn(SalarySupplement $supplement): array => array_filter(
                [
                    'codeCategorie' => $supplement->category()->value,
                    'codeSousCategorie' => $supplement->subCategory()->value,
                    'montant' => MoneyEncoding::encode($supplement->amount()),
                    'salaireHoraire' => self::money($supplement->hourlyWage()),
                    'nombreHeures' => $supplement->hours(),
                ],
                static fn(mixed $value): bool => $value !== null,
            ),
            $supplements,
        );
    }

    private static function money(?Money $money): ?JsonDecimal
    {
        return $money instanceof Money ? MoneyEncoding::encode($money) : null;
    }

    private static function days(?HalfDays $days): ?JsonDecimal
    {
        return $days instanceof HalfDays ? QuantityEncoding::encodeHalfDays($days) : null;
    }

    private static function coefficient(?Coefficient $coefficient): ?JsonDecimal
    {
        return $coefficient instanceof Coefficient ? QuantityEncoding::encodeCoefficient($coefficient) : null;
    }
}
