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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressRule;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class AddressTest extends TestCase
{
    public function testOfficialExampleAndAccessors(): void
    {
        $address = EmployerSamples::address();

        static::assertSame(12, $address->number()?->value());
        static::assertSame('B', $address->letter()?->value());
        static::assertSame('R', $address->type()?->value());
        static::assertSame('De la Paix', $address->streetName()?->value());
        static::assertSame('Batiment C', $address->complement()?->value());
        static::assertNull($address->locality());
        static::assertSame('69383', $address->municipalityCode()?->value());
        static::assertSame('69003', $address->postalCode()->value());
    }

    public function testMinimalAddresses(): void
    {
        $postal = PostalCode::fromString('69003');

        foreach ([
            new Address(null, null, null, AddressText::streetName('Rue'), null, null, null, $postal),
            new Address(null, null, null, null, AddressText::complement('Batiment C'), null, null, $postal),
            new Address(null, null, null, null, null, AddressText::locality('Les Granges'), null, $postal),
        ] as $address) {
            static::assertNull($address->municipalityCode());
            static::assertSame('69003', $address->postalCode()->value());
        }
    }

    public function testLongestStreetLineFitsThirtyTwoCharacters(): void
    {
        $name = AddressText::streetName(str_repeat('é', AddressText::STREET_NAME_MAX_LENGTH));
        $address = new Address(
            StreetNumber::fromInt(9999),
            StreetLetter::fromString('B'),
            StreetType::fromString('RES'),
            $name,
            null,
            null,
            null,
            PostalCode::fromString('69003'),
        );

        $number = $address->number();
        $letter = $address->letter();
        $type = $address->type();
        $street = $address->streetName();
        static::assertNotNull($number);
        static::assertNotNull($letter);
        static::assertNotNull($type);
        static::assertNotNull($street);
        $line = $number->value() . $letter->value() . ' ' . $type->value() . ' ' . $street->value();

        static::assertLessThanOrEqual(Address::STREET_LINE_MAX_LENGTH, mb_strlen($line));
        static::assertSame(31, mb_strlen($line));
    }

    /**
     * @return iterable<string, array{array<string, bool>, string}>
     */
    public static function provideViolations(): iterable
    {
        yield 'no location at all' => [[], 'address.location_required'];
        yield 'letter without number' => [['letter' => true, 'name' => true], 'street_number.required_with_street_letter'];
        yield 'number without name' => [['number' => true, 'complement' => true], 'street_name.required_with_street_number'];
        yield 'type without name' => [['type' => true, 'locality' => true], 'street_name.required_with_street_type'];
    }

    /**
     * @param array<string, bool> $parts
     */
    #[DataProvider('provideViolations')]
    public function testInvalidCombinations(array $parts, string $code): void
    {
        $exception = DomainAssertions::assertViolationCode(
            $code,
            static fn(): Address => new Address(
                ($parts['number'] ?? false) ? StreetNumber::fromInt(1) : null,
                ($parts['letter'] ?? false) ? StreetLetter::fromString('A') : null,
                ($parts['type'] ?? false) ? StreetType::fromString('R') : null,
                ($parts['name'] ?? false) ? AddressText::streetName('Paix') : null,
                ($parts['complement'] ?? false) ? AddressText::complement('Batiment') : null,
                ($parts['locality'] ?? false) ? AddressText::locality('Lieu') : null,
                null,
                PostalCode::fromString('69003'),
            ),
        );

        static::assertInstanceOf(InvalidValue::class, $exception);
        static::assertSame([], $exception->params());
        foreach (AddressRule::cases() as $rule) {
            if ($rule->code() !== $code) {
                continue;
            }
            static::assertSame($rule->message(), $exception->getMessage());
        }
    }

    public function testTheFirstViolatedRuleWins(): void
    {
        static::assertInstanceOf(InvalidValue::class, DomainAssertions::assertViolationCode(
            'street_name.required_with_street_number',
            static fn(): Address => new Address(
                StreetNumber::fromInt(1),
                null,
                StreetType::fromString('R'),
                null,
                null,
                null,
                null,
                PostalCode::fromString('69003'),
            ),
        ));
    }

    public function testEquality(): void
    {
        $address = EmployerSamples::address();
        $postal = PostalCode::fromString('69003');
        $number = StreetNumber::fromInt(12);
        $letter = StreetLetter::fromString('B');
        $type = StreetType::fromString('R');
        $name = AddressText::streetName('De la Paix');
        $complement = AddressText::complement('Batiment C');
        $municipality = MunicipalityCode::fromString('69383');

        static::assertTrue($address->equals(EmployerSamples::address()));
        static::assertFalse($address->equals(new Address(StreetNumber::fromInt(13), $letter, $type, $name, $complement, null, $municipality, $postal)));
        static::assertFalse($address->equals(new Address($number, StreetLetter::fromString('C'), $type, $name, $complement, null, $municipality, $postal)));
        static::assertFalse($address->equals(new Address($number, $letter, StreetType::fromString('AV'), $name, $complement, null, $municipality, $postal)));
        static::assertFalse($address->equals(new Address($number, $letter, $type, AddressText::streetName('Autre'), $complement, null, $municipality, $postal)));
        static::assertFalse($address->equals(new Address($number, $letter, $type, $name, AddressText::complement('Autre'), null, $municipality, $postal)));
        static::assertFalse($address->equals(new Address($number, $letter, $type, $name, $complement, AddressText::locality('Lieu'), $municipality, $postal)));
        static::assertFalse($address->equals(new Address($number, $letter, $type, $name, $complement, null, MunicipalityCode::fromString('69384'), $postal)));
        static::assertFalse($address->equals(new Address($number, $letter, $type, $name, $complement, null, null, $postal)));
        static::assertFalse($address->equals(new Address($number, $letter, $type, $name, $complement, null, $municipality, PostalCode::fromString('69004'))));
        static::assertFalse($address->equals(new Address(null, null, null, $name, $complement, null, $municipality, $postal)));
    }
}
