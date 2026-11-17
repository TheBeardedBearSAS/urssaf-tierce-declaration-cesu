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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\QueryEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\RequestSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\UnsupportedEmployeeReference;

final class QueryEncodingEmployeeTest extends TestCase
{
    private const NIR = '269054958815780';

    private static function employer(): Employer
    {
        return Employer::of(CesuNumber::fromString('Z0003752390009'), CalendarDate::fromString('1975-01-30'));
    }

    public function testVerifyEmployerByEmployeeCesuNumber(): void
    {
        $reference = EmployeeByCesuNumber::of(CesuNumber::fromString('00000123456789'));

        static::assertSame(
            ['dateNaissance' => '1975-01-30', 'numeroCesuSala' => '00000123456789'],
            QueryEncoding::employerVerification(EmployerVerification::forPair(self::employer(), $reference)),
        );
    }

    public function testVerifyEmployerByEmployeeNirWithIdentityIsSentAsNirOnly(): void
    {
        $reference = EmployeeByNir::of(Nir::fromString(self::NIR), RequestSamples::fullIdentity());

        static::assertSame(
            ['dateNaissance' => '1975-01-30', 'nirSala' => self::NIR],
            QueryEncoding::employerVerification(EmployerVerification::forPair(self::employer(), $reference)),
        );
    }

    public function testVerifyEmployerByEmployeeNirWithoutIdentity(): void
    {
        $reference = EmployeeByNir::of(Nir::fromString(self::NIR), null);

        static::assertSame(
            ['dateNaissance' => '1975-01-30', 'nirSala' => self::NIR],
            QueryEncoding::employerVerification(EmployerVerification::forPair(self::employer(), $reference)),
        );
    }

    public function testVerifyEmployerByEmployeeIdentity(): void
    {
        $reference = EmployeeByIdentity::of(RequestSamples::fullIdentity());

        static::assertSame(
            [
                'dateNaissance' => '1975-01-30',
                'prenomSala' => 'Julie',
                'nomNaissanceSala' => 'Martin',
                'nomUsageSala' => 'Durand',
                'dateNaissanceSala' => '1990-05-12',
                'departementNaissanceSala' => '69',
                'villeNaissanceSala' => 'Lyon',
                'codePaysNaissanceSala' => '100',
            ],
            QueryEncoding::employerVerification(EmployerVerification::forPair(self::employer(), $reference)),
        );
    }

    public function testEmployeeParametersAreSuffixed(): void
    {
        static::assertSame(
            ['numeroCesuX' => '00000123456789'],
            QueryEncoding::employeeParameters(EmployeeByCesuNumber::of(CesuNumber::fromString('00000123456789')), 'X'),
        );
    }

    public function testVerifyEmployeeWithoutSuffix(): void
    {
        static::assertSame(
            ['numeroCesu' => '00000123456789'],
            QueryEncoding::employeeVerification(EmployeeByCesuNumber::of(CesuNumber::fromString('00000123456789'))),
        );
        static::assertSame(
            ['nir' => self::NIR],
            QueryEncoding::employeeVerification(EmployeeByNir::of(Nir::fromString(self::NIR), RequestSamples::fullIdentity())),
        );
        static::assertSame(
            [
                'prenom' => 'Julie',
                'nomNaissance' => 'Martin',
                'nomUsage' => 'Durand',
                'dateNaissance' => '1990-05-12',
                'departementNaissance' => '69',
                'villeNaissance' => 'Lyon',
                'codePaysNaissance' => '100',
            ],
            QueryEncoding::employeeVerification(EmployeeByIdentity::of(RequestSamples::fullIdentity())),
        );
    }

    public function testIdentityParametersOmitAbsentOptionalValues(): void
    {
        static::assertSame(
            ['prenomSala' => 'Julie', 'nomNaissanceSala' => 'Martin', 'codePaysNaissanceSala' => '350'],
            QueryEncoding::identityParameters(RequestSamples::minimalIdentity(), 'Sala'),
        );
    }

    public function testIdentityParametersKeepEachOptionalValueIndependently(): void
    {
        $birthDateOnly = new EmployeeIdentity(
            PersonName::birthName('Martin'),
            null,
            PersonName::firstName('Julie'),
            CalendarDate::fromString('1990-05-12'),
            null,
            CountryCode::fromString('350'),
            null,
        );
        $cityOnly = new EmployeeIdentity(
            PersonName::birthName('Martin'),
            null,
            PersonName::firstName('Julie'),
            null,
            PersonName::birthCity('Lyon'),
            CountryCode::fromString('350'),
            null,
        );

        static::assertSame(
            ['prenom' => 'Julie', 'nomNaissance' => 'Martin', 'dateNaissance' => '1990-05-12', 'codePaysNaissance' => '350'],
            QueryEncoding::identityParameters($birthDateOnly, ''),
        );
        static::assertSame(
            ['prenom' => 'Julie', 'nomNaissance' => 'Martin', 'villeNaissance' => 'Lyon', 'codePaysNaissance' => '350'],
            QueryEncoding::identityParameters($cityOnly, ''),
        );
    }

    public function testAnUnsupportedReferenceIsRefused(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'wire.unsupported_employee_reference',
            static fn(): array => QueryEncoding::employeeVerification(new UnsupportedEmployeeReference()),
        );

        static::assertSame('The employee reference has no wire encoding.', $exception->getMessage());
    }
}
