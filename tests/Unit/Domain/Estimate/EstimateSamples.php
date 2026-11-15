<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ExemptionCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\GeographicZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SalaryType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedHourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;

/**
 * Hand-written samples of the estimate scope.
 */
final class EstimateSamples
{
    public static function hourlyIncome(int $workedHours = 12, ?int $first = 0, ?int $second = 0): SimulatedHourlyIncome
    {
        return new SimulatedHourlyIncome(
            Money::fromCents(1000),
            WorkedHours::of($workedHours, $first, $second),
            0,
            [],
            SalaryType::Net,
            ExemptionCode::NoExemption,
            GeographicZone::Metropolitan,
            true,
        );
    }

    public static function supplement(): SalarySupplement
    {
        return DeclarationSamples::supplement();
    }
}
