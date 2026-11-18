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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class StreetTypeTest extends TestCase
{
    private const CODES = [
        'ALL', 'AV', 'BAT', 'BD', 'CAN', 'CHS', 'CHE', 'CI', 'CL', 'C', 'COG', 'FG', 'HAM', 'IMB', 'IMP', 'LOT', 'MAI',
        'MTE', 'PAS', 'PAR', 'PAV', 'PL', 'POR', 'Q', 'QU', 'RAM', 'RES', 'RTE', 'R', 'RUL', 'SEN', 'SQ', 'VO',
    ];

    public function testThePublishedCodesAreAccepted(): void
    {
        static::assertCount(33, self::CODES);
        foreach (self::CODES as $code) {
            static::assertSame($code, StreetType::fromString($code)->value());
            static::assertSame($code, (string) StreetType::fromString($code));
            static::assertLessThanOrEqual(StreetType::MAX_LENGTH, \strlen($code));
        }
    }

    public function testCodesAreUpperCased(): void
    {
        static::assertSame('RES', StreetType::fromString('res')->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown' => ['XYZ'];
        yield 'long name' => ['RUE'];
        yield 'padded' => [' R'];
        yield 'trailing newline' => ["R\n"];
    }

    #[DataProvider('provideUnknownCodes')]
    public function testUnknownCodes(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('street_type'));

        StreetType::fromString($code);
    }

    public function testEquality(): void
    {
        static::assertTrue(StreetType::fromString('R')->equals(StreetType::fromString('r')));
        static::assertFalse(StreetType::fromString('R')->equals(StreetType::fromString('AV')));
    }
}
