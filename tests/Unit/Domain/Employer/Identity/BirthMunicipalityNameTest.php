<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Identity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\BirthMunicipalityName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class BirthMunicipalityNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidNames(): iterable
    {
        yield 'official example' => ['LYON 01'];
        yield 'apostrophe and hyphen' => ["L'Haÿ-les-Roses"];
        yield 'digits' => ['Paris 15e'];
        yield 'sixty characters' => [str_repeat('a', 60)];
        yield 'sixty multibyte characters' => [str_repeat('é', 60)];
    }

    #[DataProvider('provideValidNames')]
    public function testValidNamesAreKeptVerbatim(string $value): void
    {
        static::assertSame($value, BirthMunicipalityName::fromString($value)->value());
    }

    /**
     * @return iterable<string, array{string, InvalidValue}>
     */
    public static function provideRefusedNames(): iterable
    {
        yield 'empty' => ['', InvalidValue::empty('birth_municipality_name')];
        yield 'spaces' => ['   ', InvalidValue::empty('birth_municipality_name')];
        yield 'tab and newline' => ["\t\n", InvalidValue::empty('birth_municipality_name')];
        yield 'tab inside' => ["Ly\ton", InvalidValue::invalidCharacters('birth_municipality_name')];
        yield 'trailing newline' => ["Lyon\n", InvalidValue::invalidCharacters('birth_municipality_name')];
        yield 'invalid utf-8' => ["Ly\xFFon", InvalidValue::invalidCharacters('birth_municipality_name')];
        yield 'sixty-one characters' => [str_repeat('a', 61), InvalidValue::tooLong('birth_municipality_name', 60)];
        yield 'sixty-one multibyte characters' => [str_repeat('é', 61), InvalidValue::tooLong('birth_municipality_name', 60)];
        yield 'invalid and too long: invalid wins' => ["\t" . str_repeat('a', 61), InvalidValue::invalidCharacters('birth_municipality_name')];
    }

    #[DataProvider('provideRefusedNames')]
    public function testRefusedNames(string $value, InvalidValue $expected): void
    {
        $this->expectExceptionObject($expected);

        BirthMunicipalityName::fromString($value);
    }

    public function testEquality(): void
    {
        $name = BirthMunicipalityName::fromString('LYON 01');

        static::assertTrue($name->equals(BirthMunicipalityName::fromString('LYON 01')));
        static::assertFalse($name->equals(BirthMunicipalityName::fromString('LYON 02')));
    }

    public function testDebugOutputIsMasked(): void
    {
        $name = BirthMunicipalityName::fromString('LYON 01');

        static::assertSame(['value' => '***'], $name->__debugInfo());
        static::assertStringNotContainsString('LYON', print_r($name, true));
    }
}
