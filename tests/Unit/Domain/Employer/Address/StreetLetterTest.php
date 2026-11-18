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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class StreetLetterTest extends TestCase
{
    public function testLettersAreUpperCased(): void
    {
        static::assertSame('B', StreetLetter::fromString('b')->value());
        static::assertSame('A', (string) StreetLetter::fromString('A'));
        static::assertSame('Z', StreetLetter::fromString('Z')->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'two letters' => ['AB'];
        yield 'digit' => ['1'];
        yield 'accented' => ['é'];
        yield 'space' => [' '];
        yield 'trailing newline' => ["A\n"];
        yield 'leading space' => [' A'];
    }

    #[DataProvider('provideInvalidValues')]
    public function testInvalidValues(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('street_letter', 'a single letter from A to Z'));

        StreetLetter::fromString($value);
    }

    public function testEquality(): void
    {
        $letter = StreetLetter::fromString('A');

        static::assertTrue($letter->equals(StreetLetter::fromString('a')));
        static::assertFalse($letter->equals(StreetLetter::fromString('B')));
    }
}
