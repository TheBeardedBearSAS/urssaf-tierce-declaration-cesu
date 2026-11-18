<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class WorkedHoursTest extends TestCase
{
    public function testKeepsItsHours(): void
    {
        $hours = WorkedHours::of(100, 10, 5);

        static::assertSame(100, $hours->workedHours());
        static::assertSame(10, $hours->firstTierOvertimeHours());
        static::assertSame(5, $hours->secondTierOvertimeHours());
    }

    public function testOvertimeIsOptional(): void
    {
        $hours = WorkedHours::of(0, null, null);

        static::assertSame(0, $hours->workedHours());
        static::assertNull($hours->firstTierOvertimeHours());
        static::assertNull($hours->secondTierOvertimeHours());
    }

    public function testMaxima(): void
    {
        $hours = WorkedHours::of(200, 35, 40);

        static::assertSame([200, 35, 40], [$hours->workedHours(), $hours->firstTierOvertimeHours(), $hours->secondTierOvertimeHours()]);
        $zero = WorkedHours::of(0, 0, 0);
        static::assertSame([0, 0, 0], [$zero->workedHours(), $zero->firstTierOvertimeHours(), $zero->secondTierOvertimeHours()]);
    }

    /**
     * @return iterable<string, array{int, int|null, int|null, string, int}>
     */
    public static function provideOutOfRangeHours(): iterable
    {
        yield 'worked hours below' => [-1, null, null, 'worked_hours.out_of_range', 200];
        yield 'worked hours above' => [201, null, null, 'worked_hours.out_of_range', 200];
        yield 'first tier below' => [10, -1, null, 'first_tier_overtime_hours.out_of_range', 35];
        yield 'first tier above' => [10, 36, null, 'first_tier_overtime_hours.out_of_range', 35];
        yield 'second tier below' => [10, null, -1, 'second_tier_overtime_hours.out_of_range', 40];
        yield 'second tier above' => [10, null, 41, 'second_tier_overtime_hours.out_of_range', 40];
    }

    #[DataProvider('provideOutOfRangeHours')]
    public function testOutOfRangeHoursAreRefused(int $worked, ?int $first, ?int $second, string $code, int $max): void
    {
        $exception = DomainAssertions::assertViolationCode($code, static fn(): WorkedHours => WorkedHours::of($worked, $first, $second));

        static::assertSame(['min' => 0, 'max' => $max], $exception->params());
    }

    public function testOvertimeDefaultsToZeroWhenNotGiven(): void
    {
        $absent = WorkedHours::of(10, null, null);
        $given = WorkedHours::of(10, 7, 3);

        static::assertSame([0, 0], [$absent->firstTierOvertimeHoursOrZero(), $absent->secondTierOvertimeHoursOrZero()]);
        static::assertSame([7, 3], [$given->firstTierOvertimeHoursOrZero(), $given->secondTierOvertimeHoursOrZero()]);
    }

    public function testEquality(): void
    {
        $hours = WorkedHours::of(100, 10, 5);

        static::assertTrue($hours->equals(WorkedHours::of(100, 10, 5)));
        static::assertFalse($hours->equals(WorkedHours::of(101, 10, 5)));
        static::assertFalse($hours->equals(WorkedHours::of(100, 11, 5)));
        static::assertFalse($hours->equals(WorkedHours::of(100, 10, 6)));
        static::assertFalse($hours->equals(WorkedHours::of(100, null, 5)));
    }
}
