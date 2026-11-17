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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\BirthDepartment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class EmployeeIdentityTest extends TestCase
{
    public function testAccessors(): void
    {
        $identity = EmployeeSamples::identity();

        static::assertSame('Martin', $identity->birthName()->value());
        static::assertSame('Durand', $identity->usageName()?->value());
        static::assertSame('Julie', $identity->firstName()->value());
        static::assertSame('1990-05-12', $identity->birthDate()?->toString());
        static::assertSame('Lyon', $identity->birthCity()?->value());
        static::assertSame('100', $identity->birthCountry()->value());
        static::assertSame('69', $identity->birthDepartment()?->value());
    }

    public function testBornInFranceRequiresADepartment(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            EmployeeIdentity::BIRTH_DEPARTMENT_REQUIRED,
            static fn(): EmployeeIdentity => new EmployeeIdentity(
                PersonName::birthName('Martin'),
                null,
                PersonName::firstName('Julie'),
                null,
                null,
                CountryCode::france(),
                null,
            ),
        );

        static::assertSame('employee_identity.birth_department_required', $exception->violationCode());
        static::assertSame('The birth department is required for an employee born in France.', $exception->getMessage());
    }

    public function testBornAbroadNeedsNoDepartmentAndAcceptsOne(): void
    {
        $abroad = CountryCode::fromString('212');

        $without = new EmployeeIdentity(PersonName::birthName('Martin'), null, PersonName::firstName('Julie'), null, null, $abroad, null);
        $with = new EmployeeIdentity(PersonName::birthName('Martin'), null, PersonName::firstName('Julie'), null, null, $abroad, BirthDepartment::fromString('99'));

        static::assertNull($without->birthDepartment());
        static::assertSame('99', $with->birthDepartment()?->value());
    }

    /**
     * @param array{birthName?: PersonName, usageName?: PersonName|null, firstName?: PersonName, birthDate?: CalendarDate|null, birthCity?: PersonName|null, birthCountry?: CountryCode, birthDepartment?: BirthDepartment} $changes
     */
    private static function variant(array $changes): EmployeeIdentity
    {
        return new EmployeeIdentity(
            $changes['birthName'] ?? PersonName::birthName('Martin'),
            \array_key_exists('usageName', $changes) ? $changes['usageName'] : PersonName::usageName('Durand'),
            $changes['firstName'] ?? PersonName::firstName('Julie'),
            \array_key_exists('birthDate', $changes) ? $changes['birthDate'] : CalendarDate::fromString('1990-05-12'),
            \array_key_exists('birthCity', $changes) ? $changes['birthCity'] : PersonName::birthCity('Lyon'),
            $changes['birthCountry'] ?? CountryCode::france(),
            $changes['birthDepartment'] ?? BirthDepartment::fromString('69'),
        );
    }

    public function testEquality(): void
    {
        $identity = EmployeeSamples::identity();

        static::assertTrue($identity->equals(self::variant([])));
        static::assertFalse($identity->equals(self::variant(['birthName' => PersonName::birthName('Autre')])));
        static::assertFalse($identity->equals(self::variant(['usageName' => PersonName::usageName('Autre')])));
        static::assertFalse($identity->equals(self::variant(['usageName' => null])));
        static::assertFalse($identity->equals(self::variant(['firstName' => PersonName::firstName('Autre')])));
        static::assertFalse($identity->equals(self::variant(['birthDate' => CalendarDate::fromString('1990-05-13')])));
        static::assertFalse($identity->equals(self::variant(['birthDate' => null])));
        static::assertFalse($identity->equals(self::variant(['birthCity' => PersonName::birthCity('Autre')])));
        static::assertFalse($identity->equals(self::variant(['birthCity' => null])));
        static::assertFalse($identity->equals(self::variant(['birthCountry' => CountryCode::fromString('212')])));
        static::assertFalse($identity->equals(self::variant(['birthDepartment' => BirthDepartment::fromString('75')])));
        static::assertTrue(self::variant(['usageName' => null, 'birthDate' => null, 'birthCity' => null])->equals(self::variant(['usageName' => null, 'birthDate' => null, 'birthCity' => null])));
    }

    public function testEqualityWithMissingDepartment(): void
    {
        $abroad = CountryCode::fromString('212');
        $withoutBis = new EmployeeIdentity(
            PersonName::birthName('Martin'),
            PersonName::usageName('Durand'),
            PersonName::firstName('Julie'),
            CalendarDate::fromString('1990-05-12'),
            PersonName::birthCity('Lyon'),
            $abroad,
            null,
        );
        $with = self::variant(['birthCountry' => $abroad, 'birthDepartment' => BirthDepartment::fromString('99')]);
        $withoutAny = new EmployeeIdentity(PersonName::birthName('Martin'), null, PersonName::firstName('Julie'), null, null, $abroad, null);

        static::assertTrue($withoutBis->equals($withoutBis));
        static::assertTrue($withoutAny->equals($withoutAny));
        static::assertFalse($with->equals($withoutBis));
        static::assertFalse($withoutBis->equals($with));
        static::assertFalse($withoutAny->equals($with));
        static::assertFalse($with->equals($withoutAny));
    }

    public function testBirthDateIsMaskedInDebugOutput(): void
    {
        $identity = EmployeeSamples::identity();

        static::assertSame(
            [
                'birthName' => $identity->birthName(),
                'usageName' => $identity->usageName(),
                'firstName' => $identity->firstName(),
                'birthDate' => '***',
                'birthCity' => $identity->birthCity(),
                'birthCountry' => $identity->birthCountry(),
                'birthDepartment' => $identity->birthDepartment(),
            ],
            $identity->__debugInfo(),
        );
        $dump = print_r($identity, true);
        foreach (['1990', 'Martin', 'Durand', 'Julie', 'Lyon'] as $secret) {
            static::assertStringNotContainsString($secret, $dump);
        }
    }

    public function testAMissingBirthDateStaysNullInDebugOutput(): void
    {
        $identity = self::variant(['birthDate' => null]);

        static::assertNull($identity->__debugInfo()['birthDate']);
    }
}
