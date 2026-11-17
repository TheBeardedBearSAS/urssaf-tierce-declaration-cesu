<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\BirthMunicipalityName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\CivilName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ExemptionCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\GeographicZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SalaryType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedHourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Fictitious requests of the gateway tests (documentation examples and invented data only).
 */
final class GatewaySamples
{
    private function __construct() {}

    public static function employer(): Employer
    {
        return Employer::of(CesuNumber::fromString('Z0003752390009'), CalendarDate::fromString('1975-01-30'));
    }

    /**
     * The estimate example of the PDF (api-reference §2.6).
     */
    public static function estimate(): EstimateRequest
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

    public static function cesuPlusActivation(bool $agreement): CesuPlusActivationRequest
    {
        return CesuPlusActivationRequest::of(self::employer(), CesuNumber::fromString('00000123456789'), $agreement);
    }

    /**
     * The activation example of the PDF (api-reference §2.9).
     */
    public static function aiciActivation(): AiciActivationRequest
    {
        return new AiciActivationRequest(
            Employer::of(CesuNumber::fromString('Z0003752390009'), CalendarDate::fromString('2019-01-30')),
            Civility::Male,
            CivilName::birthName('Dupond'),
            CivilName::usageName('Dupond'),
            CivilName::firstName('Marcel'),
            CountryCode::france(),
            MunicipalityCode::fromString('69381'),
            BirthMunicipalityName::fromString('LYON 01'),
        );
    }
}
