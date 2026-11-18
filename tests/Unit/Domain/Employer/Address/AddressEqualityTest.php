<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Address;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Equality of addresses whose optional parts are missing on one side, on the other side or on both.
 */
final class AddressEqualityTest extends TestCase
{
    public function testMissingPartsOnBothSides(): void
    {
        foreach ([self::nameOnly(), self::complementOnly(), self::localityOnly()] as $minimal) {
            static::assertTrue($minimal->equals($minimal));
        }
    }

    public function testMissingPartsOnOneSideOnly(): void
    {
        $full = self::full();

        foreach ([self::nameOnly(), self::complementOnly(), self::localityOnly()] as $minimal) {
            static::assertFalse($full->equals($minimal));
            static::assertFalse($minimal->equals($full));
        }
    }

    public function testDifferentMinimalAddresses(): void
    {
        static::assertFalse(self::nameOnly()->equals(self::complementOnly()));
        static::assertFalse(self::complementOnly()->equals(self::localityOnly()));
    }

    /**
     * @throws InvalidValue
     */
    private static function full(): Address
    {
        return new Address(
            StreetNumber::fromInt(12),
            StreetLetter::fromString('B'),
            StreetType::fromString('R'),
            AddressText::streetName('De la Paix'),
            AddressText::complement('Batiment C'),
            AddressText::locality('Les Granges'),
            MunicipalityCode::fromString('69383'),
            PostalCode::fromString('69003'),
        );
    }

    /**
     * @throws InvalidValue
     */
    private static function nameOnly(): Address
    {
        return new Address(null, null, null, AddressText::streetName('De la Paix'), null, null, null, PostalCode::fromString('69003'));
    }

    /**
     * @throws InvalidValue
     */
    private static function complementOnly(): Address
    {
        return new Address(null, null, null, null, AddressText::complement('Batiment C'), null, null, PostalCode::fromString('69003'));
    }

    /**
     * @throws InvalidValue
     */
    private static function localityOnly(): Address
    {
        return new Address(null, null, null, null, null, AddressText::locality('Les Granges'), null, PostalCode::fromString('69003'));
    }
}
