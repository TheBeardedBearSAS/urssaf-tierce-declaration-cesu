<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressValidator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class AddressValidatorTest extends TestCase
{
    public function testValidAddress(): void
    {
        $collector = new ViolationCollector();

        $address = (new AddressValidator())->parse(new AddressDraft(12, 'b', 'r', 'De la Paix', 'Batiment C', '', '69383', '69003'), $collector);

        static::assertSame([], DomainAssertions::summary($collector->result()));
        static::assertNotNull($address);
        static::assertTrue($address->equals(EmployerSamples::address()));
    }

    public function testMinimalAddress(): void
    {
        $collector = new ViolationCollector();

        $address = (new AddressValidator())->parse(new AddressDraft(null, null, null, null, null, 'Les Granges', null, '69003'), $collector);

        static::assertSame([], DomainAssertions::summary($collector->result()));
        static::assertNotNull($address);
        static::assertSame('Les Granges', $address->locality()?->value());
        static::assertNull($address->number());
        static::assertNull($address->municipalityCode());
    }

    public function testValidateReturnsTheResult(): void
    {
        $validator = new AddressValidator();

        static::assertTrue($validator->validate(new AddressDraft(null, null, null, 'Rue', null, null, null, '69003'))->isValid());
        static::assertSame(
            ['error postalCode postal_code.required'],
            DomainAssertions::summary($validator->validate(new AddressDraft(null, null, null, 'Rue', null, null, null, null))),
        );
    }

    /**
     * @return iterable<string, array{AddressDraft, list<string>}>
     */
    public static function provideInvalidDrafts(): iterable
    {
        yield 'nothing at all' => [
            new AddressDraft(null, null, null, null, null, null, null, null),
            ['error  address.location_required', 'error postalCode postal_code.required'],
        ];
        yield 'empty strings count as missing' => [
            new AddressDraft(null, '', '', '', '', '', '', ''),
            ['error  address.location_required', 'error postalCode postal_code.required'],
        ];
        yield 'only a postal code' => [
            new AddressDraft(null, null, null, null, null, null, null, '69003'),
            ['error  address.location_required'],
        ];
        yield 'every field invalid' => [
            new AddressDraft(0, 'AB', 'XYZ', str_repeat('a', 22), str_repeat('a', 33), str_repeat('a', 33), '1', '2'),
            [
                'error streetNumber street_number.out_of_range',
                'error streetLetter street_letter.invalid_format',
                'error streetType street_type.unknown',
                'error streetName street_name.too_long',
                'error complement address_complement.too_long',
                'error locality locality.too_long',
                'error municipalityCode municipality_code.invalid_format',
                'error postalCode postal_code.invalid_format',
            ],
        ];
        yield 'letter without number' => [
            new AddressDraft(null, 'A', null, 'Rue', null, null, null, '69003'),
            ['error streetNumber street_number.required_with_street_letter'],
        ];
        yield 'number without name' => [
            new AddressDraft(1, null, null, null, 'Batiment', null, null, '69003'),
            ['error streetName street_name.required_with_street_number'],
        ];
        yield 'type without name' => [
            new AddressDraft(null, null, 'R', null, null, 'Lieu', null, '69003'),
            ['error streetName street_name.required_with_street_type'],
        ];
        yield 'number and type without name' => [
            new AddressDraft(1, 'A', 'R', null, null, null, null, '69003'),
            ['error streetName street_name.required_with_street_number', 'error streetName street_name.required_with_street_type'],
        ];
        yield 'only the postal code is invalid' => [
            new AddressDraft(null, null, null, 'Rue', null, null, null, '6900'),
            ['error postalCode postal_code.invalid_format'],
        ];
        yield 'only the street number is invalid' => [
            new AddressDraft(10_000, null, null, 'Rue', null, null, null, '69003'),
            ['error streetNumber street_number.out_of_range'],
        ];
        yield 'only the municipality code is invalid' => [
            new AddressDraft(null, null, null, 'Rue', null, null, '1', '69003'),
            ['error municipalityCode municipality_code.invalid_format'],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidDrafts')]
    public function testInvalidDrafts(AddressDraft $draft, array $expected): void
    {
        $collector = new ViolationCollector();

        $address = (new AddressValidator())->parse($draft, $collector);

        static::assertNull($address);
        static::assertSame($expected, DomainAssertions::summary($collector->result()));
    }

    public function testPreviousErrorsOfTheCollectorDoNotMakeAValidAddressFail(): void
    {
        $collector = new ViolationCollector();
        (new AddressValidator())->parse(new AddressDraft(null, null, null, null, null, null, null, null), $collector);

        $address = (new AddressValidator())->parse(new AddressDraft(null, null, null, 'Rue', null, null, null, '69003'), $collector);

        static::assertNotNull($address);
        static::assertCount(2, $collector->result()->errors());
    }

    public function testViolationMessagesAreTheRuleMessages(): void
    {
        $result = (new AddressValidator())->validate(new AddressDraft(null, 'A', null, 'Rue', null, null, null, '69003'));

        static::assertSame('A street number is required when a street letter is given.', $result->errors()[0]->message());
    }
}
