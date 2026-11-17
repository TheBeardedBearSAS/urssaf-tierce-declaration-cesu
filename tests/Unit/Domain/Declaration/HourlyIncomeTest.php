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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\HourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class HourlyIncomeTest extends TestCase
{
    public function testKeepsItsParts(): void
    {
        $supplement = DeclarationSamples::supplement();
        $leave = DeclarationSamples::leave('2026-10-05', '2026-10-06');
        $hours = WorkedHours::of(100, 4, null);
        $income = HourlyIncome::of(Money::fromCents(1250), $hours, 7, [$supplement], [$leave]);

        static::assertSame(1250, $income->hourlyWage()->cents());
        static::assertSame($hours, $income->hours());
        static::assertSame(7, $income->paidLeaveHours());
        static::assertSame([$supplement], $income->supplements());
        static::assertSame([$leave], $income->paidLeavePeriods());
    }

    public function testPaidLeaveHoursMayBeAbsent(): void
    {
        static::assertNull(DeclarationSamples::hourlyIncome()->paidLeaveHours());
    }

    public function testHourlyWageBounds(): void
    {
        static::assertSame(99_999, HourlyIncome::of(Money::fromCents(99_999), WorkedHours::of(1, null, null), null, [], [])->hourlyWage()->cents());
        $exception = DomainAssertions::assertViolationCode(
            'hourly_wage.out_of_range',
            static fn(): HourlyIncome => HourlyIncome::of(Money::fromCents(100_000), WorkedHours::of(1, null, null), null, [], []),
        );

        static::assertSame(['min' => '0.00', 'max' => '999.99'], $exception->params());
    }

    /**
     * @return iterable<string, array{int, string|null}>
     */
    public static function providePaidLeaveHours(): iterable
    {
        yield 'below' => [-1, 'paid_leave_hours.out_of_range'];
        yield 'zero' => [0, null];
        yield 'maximum' => [200, null];
        yield 'above' => [201, 'paid_leave_hours.out_of_range'];
    }

    #[DataProvider('providePaidLeaveHours')]
    public function testPaidLeaveHoursBounds(int $paidLeaveHours, ?string $violationCode): void
    {
        if ($violationCode === null) {
            static::assertSame($paidLeaveHours, DeclarationSamples::hourlyIncomeWithLeaveHours($paidLeaveHours)->paidLeaveHours());

            return;
        }
        $exception = DomainAssertions::assertViolationCode(
            $violationCode,
            static fn(): HourlyIncome => DeclarationSamples::hourlyIncomeWithLeaveHours($paidLeaveHours),
        );

        static::assertSame(['min' => 0, 'max' => 200], $exception->params());
    }

    public function testWageIsCheckedBeforePaidLeaveHours(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'hourly_wage.out_of_range',
            static fn(): HourlyIncome => HourlyIncome::of(Money::fromCents(100_000), WorkedHours::of(1, null, null), 201, [], []),
        );
        static::assertNotSame('', $exception->getMessage());
    }
}
