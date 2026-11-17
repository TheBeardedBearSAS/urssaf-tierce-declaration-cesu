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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressRule;

/**
 * @phpstan-import-type Presence from AddressRule
 */
final class AddressRuleTest extends TestCase
{
    /**
     * @param array{streetNumber?: bool, streetLetter?: bool, streetType?: bool, streetName?: bool, complement?: bool, locality?: bool} $overrides
     *
     * @return Presence
     */
    private static function presence(array $overrides): array
    {
        return [
            'streetNumber' => $overrides['streetNumber'] ?? false,
            'streetLetter' => $overrides['streetLetter'] ?? false,
            'streetType' => $overrides['streetType'] ?? false,
            'streetName' => $overrides['streetName'] ?? false,
            'complement' => $overrides['complement'] ?? false,
            'locality' => $overrides['locality'] ?? false,
        ];
    }

    /**
     * @return iterable<string, array{array{streetNumber?: bool, streetLetter?: bool, streetType?: bool, streetName?: bool, complement?: bool, locality?: bool}, list<AddressRule>}>
     */
    public static function providePresences(): iterable
    {
        yield 'nothing' => [[], [AddressRule::LocationRequired]];
        yield 'letter alone' => [['streetLetter' => true], [AddressRule::LetterRequiresNumber]];
        yield 'letter and number' => [
            ['streetLetter' => true, 'streetNumber' => true],
            [AddressRule::NumberRequiresStreetName],
        ];
        yield 'number alone' => [['streetNumber' => true], [AddressRule::NumberRequiresStreetName]];
        yield 'type alone' => [['streetType' => true], [AddressRule::StreetTypeRequiresStreetName]];
        yield 'number and type' => [
            ['streetNumber' => true, 'streetType' => true],
            [AddressRule::NumberRequiresStreetName, AddressRule::StreetTypeRequiresStreetName],
        ];
        yield 'letter, number, type' => [
            ['streetLetter' => true, 'streetNumber' => true, 'streetType' => true],
            [AddressRule::NumberRequiresStreetName, AddressRule::StreetTypeRequiresStreetName],
        ];
        yield 'name alone' => [['streetName' => true], []];
        yield 'complement alone' => [['complement' => true], []];
        yield 'locality alone' => [['locality' => true], []];
        yield 'full street' => [
            ['streetNumber' => true, 'streetLetter' => true, 'streetType' => true, 'streetName' => true],
            [],
        ];
        yield 'name and letter without number' => [
            ['streetName' => true, 'streetLetter' => true],
            [AddressRule::LetterRequiresNumber],
        ];
    }

    /**
     * @param array{streetNumber?: bool, streetLetter?: bool, streetType?: bool, streetName?: bool, complement?: bool, locality?: bool} $overrides
     * @param list<AddressRule>                                                                                                         $expected
     */
    #[DataProvider('providePresences')]
    public function testViolatedBy(array $overrides, array $expected): void
    {
        $presence = self::presence($overrides);

        static::assertSame($expected, AddressRule::violatedBy($presence));
        foreach (AddressRule::cases() as $rule) {
            static::assertSame(\in_array($rule, $expected, true), $rule->isViolatedBy($presence), $rule->name);
        }
    }

    public function testEachSingleFieldSatisfiesTheLocationRule(): void
    {
        foreach ([
            self::presence(['streetNumber' => true]),
            self::presence(['streetLetter' => true]),
            self::presence(['streetType' => true]),
            self::presence(['streetName' => true]),
            self::presence(['complement' => true]),
            self::presence(['locality' => true]),
        ] as $index => $presence) {
            static::assertFalse(AddressRule::LocationRequired->isViolatedBy($presence), (string) $index);
        }
    }

    public function testPathsCodesAndMessages(): void
    {
        static::assertSame('streetNumber', AddressRule::LetterRequiresNumber->path());
        static::assertSame('streetName', AddressRule::NumberRequiresStreetName->path());
        static::assertSame('streetName', AddressRule::StreetTypeRequiresStreetName->path());
        static::assertSame('', AddressRule::LocationRequired->path());

        static::assertSame('street_number.required_with_street_letter', AddressRule::LetterRequiresNumber->code());
        static::assertSame('street_name.required_with_street_number', AddressRule::NumberRequiresStreetName->code());
        static::assertSame('street_name.required_with_street_type', AddressRule::StreetTypeRequiresStreetName->code());
        static::assertSame('address.location_required', AddressRule::LocationRequired->code());

        static::assertSame('A street number is required when a street letter is given.', AddressRule::LetterRequiresNumber->message());
        static::assertSame('A street name is required when a street number is given.', AddressRule::NumberRequiresStreetName->message());
        static::assertSame('A street name is required when a street type is given.', AddressRule::StreetTypeRequiresStreetName->message());
        static::assertSame(
            'At least one of the street number, letter, type, name, the complement or the locality is required.',
            AddressRule::LocationRequired->message(),
        );
    }
}
