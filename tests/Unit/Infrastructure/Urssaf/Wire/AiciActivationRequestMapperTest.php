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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\BirthMunicipalityName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\CivilName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\AiciActivationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class AiciActivationRequestMapperTest extends TestCase
{
    private static function request(
        Civility $civility,
        CountryCode $country,
        ?MunicipalityCode $code,
        ?BirthMunicipalityName $name,
    ): AiciActivationRequest {
        return new AiciActivationRequest(
            Employer::of(CesuNumber::fromString('Z0003752390009'), CalendarDate::fromString('2019-01-30')),
            $civility,
            CivilName::birthName('Dupond'),
            CivilName::usageName('Dupond'),
            CivilName::firstName('Marcel'),
            $country,
            $code,
            $name,
        );
    }

    public function testAssembledFixture(): void
    {
        $request = self::request(
            Civility::Male,
            CountryCode::france(),
            MunicipalityCode::fromString('69381'),
            BirthMunicipalityName::fromString('LYON 01'),
        );

        static::assertSame(
            json_encode(
                Fixtures::decoded('activate_aici_request_assembled'),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
            JsonEncoder::encode((new AiciActivationRequestMapper())->toBody($request)),
        );
    }

    public function testBirthMunicipalityIsOmittedForABirthAbroad(): void
    {
        $request = self::request(Civility::Female, CountryCode::fromString('350'), null, null);

        static::assertSame(
            '{"nomNaissance":"Dupond","nomUsage":"Dupond","prenom":"Marcel","codeCivilite":"2",'
            . '"dateNaissance":"2019-01-30","codePaysNaissance":"350"}',
            JsonEncoder::encode((new AiciActivationRequestMapper())->toBody($request)),
        );
    }

    public function testMunicipalityDataGivenForABirthAbroadIsSent(): void
    {
        $withCode = self::request(
            Civility::Male,
            CountryCode::fromString('350'),
            MunicipalityCode::fromString('69381'),
            null,
        );
        $withName = self::request(
            Civility::Male,
            CountryCode::fromString('350'),
            null,
            BirthMunicipalityName::fromString('LYON 01'),
        );
        $mapper = new AiciActivationRequestMapper();

        static::assertSame(
            '{"nomNaissance":"Dupond","nomUsage":"Dupond","prenom":"Marcel","codeCivilite":"1",'
            . '"dateNaissance":"2019-01-30","codePaysNaissance":"350","codeCommuneNaissance":"69381"}',
            JsonEncoder::encode($mapper->toBody($withCode)),
        );
        static::assertSame(
            '{"nomNaissance":"Dupond","nomUsage":"Dupond","prenom":"Marcel","codeCivilite":"1",'
            . '"dateNaissance":"2019-01-30","codePaysNaissance":"350","libelleCommuneNaissance":"LYON 01"}',
            JsonEncoder::encode($mapper->toBody($withName)),
        );
    }
}
