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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\DateEncoding;

final class DateEncodingTest extends TestCase
{
    public function testItEncodesCalendarDatesWithoutTimePart(): void
    {
        static::assertSame('1980-03-29', DateEncoding::encode(CalendarDate::fromString('1980-03-29')));
    }

    public function testItDecodesCalendarDates(): void
    {
        static::assertSame('2026-11-15', DateEncoding::decode('2026-11-15')->toString());
    }

    public function testEncodingAndDecodingRoundTrip(): void
    {
        $date = CalendarDate::fromString('2024-02-29');

        static::assertTrue($date->equals(DateEncoding::decode(DateEncoding::encode($date))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDates(): iterable
    {
        yield 'zulu time' => ['2026-11-15T00:00:00Z'];
        yield 'zulu suffix' => ['2026-11-15Z'];
        yield 'offset' => ['2026-11-15+01:00'];
        yield 'impossible date' => ['2026-02-30'];
        yield 'french format' => ['15/11/2026'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidDates')]
    public function testItRejectsAnythingButAStrictCalendarDate(string $value): void
    {
        try {
            DateEncoding::decode($value);
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame('Unexpected URSSAF response: date is not a Y-m-d calendar date.', $exception->getMessage());
            static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
        }
    }
}
