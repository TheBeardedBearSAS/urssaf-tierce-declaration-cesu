<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\BirthDepartment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeIdentityDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;

/**
 * Fictitious values (employee Cesu number 00000123456789, NIR of the PDF 021 example).
 */
final class EmployeeSamples
{
    public const NIR = '269054958815780';

    public static function cesuNumber(): CesuNumber
    {
        return CesuNumber::fromString('00000123456789');
    }

    public static function identity(): EmployeeIdentity
    {
        return new EmployeeIdentity(
            PersonName::birthName('Martin'),
            PersonName::usageName('Durand'),
            PersonName::firstName('Julie'),
            CalendarDate::fromString('1990-05-12'),
            PersonName::birthCity('Lyon'),
            CountryCode::france(),
            BirthDepartment::fromString('69'),
        );
    }

    public static function identityDraft(): EmployeeIdentityDraft
    {
        return new EmployeeIdentityDraft('Martin', 'Durand', 'Julie', '1990-05-12', 'Lyon', '69', '100');
    }

    public static function byCesuNumber(): EmployeeByCesuNumber
    {
        return EmployeeByCesuNumber::of(self::cesuNumber());
    }

    public static function byNir(): EmployeeByNir
    {
        return EmployeeByNir::of(Nir::fromString(self::NIR), self::identity());
    }

    public static function byIdentity(): EmployeeByIdentity
    {
        return EmployeeByIdentity::of(self::identity());
    }
}
