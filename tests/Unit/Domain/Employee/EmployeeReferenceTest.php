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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class EmployeeReferenceTest extends TestCase
{
    public function testByCesuNumber(): void
    {
        $reference = EmployeeSamples::byCesuNumber();

        static::assertSame(EmployeeReferenceKind::CesuNumber, $reference->kind());
        static::assertSame('00000123456789', $reference->cesuNumber()->value());
    }

    public function testByNir(): void
    {
        $reference = EmployeeSamples::byNir();

        static::assertSame(EmployeeReferenceKind::Nir, $reference->kind());
        static::assertNull($reference->cesuNumber());
        static::assertSame(EmployeeSamples::NIR, $reference->nir()->value());
        static::assertNotNull($reference->identity());
        static::assertNull(EmployeeByNir::of(Nir::fromString(EmployeeSamples::NIR), null)->identity());
    }

    public function testByIdentity(): void
    {
        $reference = EmployeeSamples::byIdentity();

        static::assertSame(EmployeeReferenceKind::Identity, $reference->kind());
        static::assertNull($reference->cesuNumber());
        static::assertTrue($reference->identity()->equals(EmployeeSamples::identity()));
    }

    public function testByIdentityRequiresTheBirthDateThenTheBirthCity(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'employee_identity.birth_date_required',
            static fn(): EmployeeByIdentity => EmployeeByIdentity::of(self::identity(null, null)),
        );
        static::assertSame('The birth date is required.', $exception->getMessage());
        $exception = DomainAssertions::assertViolationCode(
            'employee_identity.birth_date_required',
            static fn(): EmployeeByIdentity => EmployeeByIdentity::of(self::identity(null, PersonName::birthCity('Lyon'))),
        );
        static::assertSame('The birth date is required.', $exception->getMessage());
        $exception = DomainAssertions::assertViolationCode(
            'employee_identity.birth_city_required',
            static fn(): EmployeeByIdentity => EmployeeByIdentity::of(self::identity(CalendarDate::fromString('1990-05-12'), null)),
        );
        static::assertSame('The birth city is required.', $exception->getMessage());
        static::assertSame(EmployeeReferenceKind::Identity, EmployeeByIdentity::of(self::identity(CalendarDate::fromString('1990-05-12'), PersonName::birthCity('Lyon')))->kind());
    }

    /**
     * @throws InvalidValue
     */
    private static function identity(?CalendarDate $date, ?PersonName $city): EmployeeIdentity
    {
        return new EmployeeIdentity(
            PersonName::birthName('Martin'),
            null,
            PersonName::firstName('Julie'),
            $date,
            $city,
            CountryCode::fromString('212'),
            null,
        );
    }

    public function testEquality(): void
    {
        $byCesu = EmployeeSamples::byCesuNumber();
        $byNir = EmployeeSamples::byNir();
        $nirAlone = EmployeeByNir::of(Nir::fromString(EmployeeSamples::NIR), null);
        $byIdentity = EmployeeSamples::byIdentity();

        static::assertTrue($byCesu->equals(EmployeeByCesuNumber::of(CesuNumber::fromString('00000123456789'))));
        static::assertFalse($byCesu->equals(EmployeeByCesuNumber::of(CesuNumber::fromString('00000123456780'))));
        static::assertTrue($byNir->equals(EmployeeSamples::byNir()));
        static::assertFalse($byNir->equals(EmployeeByNir::of(Nir::fromString('269054958815781'), EmployeeSamples::identity())));
        static::assertFalse($byNir->equals($nirAlone));
        static::assertFalse($nirAlone->equals($byNir));
        static::assertTrue($nirAlone->equals(EmployeeByNir::of(Nir::fromString(EmployeeSamples::NIR), null)));
        static::assertTrue($byIdentity->equals(EmployeeSamples::byIdentity()));
        static::assertFalse($byIdentity->equals(EmployeeByIdentity::of(new EmployeeIdentity(
            PersonName::birthName('Autre'),
            null,
            PersonName::firstName('Julie'),
            CalendarDate::fromString('1990-05-12'),
            PersonName::birthCity('Lyon'),
            CountryCode::fromString('212'),
            null,
        ))));
        foreach ([[$byCesu, $byNir], [$byCesu, $byIdentity], [$byNir, $byIdentity], [$byNir, $byCesu], [$byIdentity, $byNir], [$byIdentity, $byCesu], [$byCesu, $nirAlone]] as [$left, $right]) {
            static::assertFalse($left->equals($right));
        }
    }
}
