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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class StreetNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function provideValidValues(): iterable
    {
        yield 'minimum' => [1];
        yield 'official example' => [12];
        yield 'maximum' => [9999];
    }

    #[DataProvider('provideValidValues')]
    public function testValidValues(int $value): void
    {
        static::assertSame($value, StreetNumber::fromInt($value)->value());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above maximum' => [10_000];
    }

    #[DataProvider('provideInvalidValues')]
    public function testOutOfRange(int $value): void
    {
        $this->expectExceptionObject(InvalidValue::outOfRange('street_number', 1, 9999));

        StreetNumber::fromInt($value);
    }

    public function testEqualityAndMasking(): void
    {
        $number = StreetNumber::fromInt(12);

        static::assertTrue($number->equals(StreetNumber::fromInt(12)));
        static::assertFalse($number->equals(StreetNumber::fromInt(13)));
        static::assertSame(['value' => '***'], $number->__debugInfo());
    }
}
