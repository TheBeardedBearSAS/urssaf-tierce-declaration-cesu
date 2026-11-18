<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Specification;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\HourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\PaidLeavePeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\PaidLeaveExcludedWhenIncluded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\PaidLeavePeriodsDoNotOverlap;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\PaidLeavePeriodsWithinEmployment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class PaidLeaveSpecificationsTest extends TestCase
{
    /**
     * @param list<PaidLeavePeriod> $periods
     */
    private static function income(?int $paidLeaveHours, array $periods): HourlyIncome
    {
        return HourlyIncome::of(Money::fromCents(1000), WorkedHours::of(10, null, null), $paidLeaveHours, [], $periods);
    }

    /**
     * @return iterable<string, array{bool|null, int|null, int, list<string>}>
     */
    public static function provideExclusionCases(): iterable
    {
        $hours = 'error hourlyIncome.paidLeaveHours hourly_income.paid_leave_hours_forbidden';
        $periods = 'error hourlyIncome.paidLeavePeriods hourly_income.paid_leave_periods_forbidden';

        yield 'included, nothing sent' => [true, null, 0, []];
        yield 'included, hours' => [true, 0, 0, [$hours]];
        yield 'included, periods' => [true, null, 1, [$periods]];
        yield 'included, hours and periods' => [true, 5, 1, [$hours, $periods]];
        yield 'not included, hours and periods' => [false, 5, 1, []];
        yield 'unknown flag, hours and periods' => [null, 5, 1, []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideExclusionCases')]
    public function testPaidLeaveIsExcludedWhenIncludedInTheWage(?bool $included, ?int $hours, int $periodCount, array $expected): void
    {
        $periods = \array_slice([DeclarationSamples::leave('2026-10-05', '2026-10-06')], 0, $periodCount);
        $content = DeclarationSamples::build(self::income($hours, $periods), DeclarationSamples::employee(EmploymentKind::HomeEmployment, $included), null, null, null);

        static::assertSame($expected, DomainAssertions::summary((new PaidLeaveExcludedWhenIncluded())->validate($content)));
    }

    public function testExclusionIgnoresAMissingHourlyIncome(): void
    {
        $content = DeclarationSamples::contentWithoutIncome();

        static::assertSame([], DomainAssertions::summary((new PaidLeaveExcludedWhenIncluded())->validate($content)));
    }

    public function testExclusionMessages(): void
    {
        $content = DeclarationSamples::build(self::income(1, [DeclarationSamples::leave('2026-10-05', '2026-10-06')]), DeclarationSamples::employee(EmploymentKind::HomeEmployment, true), null, null, null);
        $violations = (new PaidLeaveExcludedWhenIncluded())->validate($content)->all();

        static::assertSame('The paid leave hours must not be given when the paid leave is included in the hourly wage.', $violations[0]->message());
        static::assertSame('The paid leave periods must not be given when the paid leave is included in the hourly wage.', $violations[1]->message());
    }

    public function testPeriodsOutsideTheEmploymentPeriodAreReportedWithTheirIndex(): void
    {
        $content = DeclarationSamples::build(self::income(null, [
            DeclarationSamples::leave('2026-10-01', '2026-10-31'),
            DeclarationSamples::leave('2026-09-30', '2026-10-05'),
            DeclarationSamples::leave('2026-10-05', '2026-10-06'),
            DeclarationSamples::leave('2026-10-30', '2026-11-01'),
        ]), null, null, null, null);
        $result = (new PaidLeavePeriodsWithinEmployment())->validate($content);

        static::assertSame(
            [
                'error hourlyIncome.paidLeavePeriods[1] paid_leave_period.outside_employment_period',
                'error hourlyIncome.paidLeavePeriods[3] paid_leave_period.outside_employment_period',
            ],
            DomainAssertions::summary($result),
        );
        static::assertSame('The paid leave period must be inside the employment period.', $result->all()[0]->message());
    }

    public function testNoPeriodIsFineWithinTheEmploymentPeriod(): void
    {
        static::assertSame([], DomainAssertions::summary((new PaidLeavePeriodsWithinEmployment())->validate(DeclarationSamples::content())));
        static::assertSame([], DomainAssertions::summary((new PaidLeavePeriodsWithinEmployment())->validate(DeclarationSamples::contentWithoutIncome())));
    }

    public function testOverlappingPeriodsAreReportedOncePerLaterPeriodWithTheFirstOverlappedIndex(): void
    {
        $content = DeclarationSamples::build(self::income(null, [
            DeclarationSamples::leave('2026-10-05', '2026-10-10'),
            DeclarationSamples::leave('2026-10-10', '2026-10-12'),
            DeclarationSamples::leave('2026-10-09', '2026-10-11'),
            DeclarationSamples::leave('2026-10-20', '2026-10-21'),
            DeclarationSamples::leave('2026-10-22', '2026-10-23'),
            DeclarationSamples::leave('2026-10-01', '2026-10-02'),
        ]), null, null, null, null);
        $result = (new PaidLeavePeriodsDoNotOverlap())->validate($content);

        static::assertSame(
            [
                'error hourlyIncome.paidLeavePeriods[1] paid_leave_period.overlap',
                'error hourlyIncome.paidLeavePeriods[2] paid_leave_period.overlap',
            ],
            DomainAssertions::summary($result),
        );
        static::assertSame(['overlapped_index' => 0], $result->all()[0]->params());
        static::assertSame(['overlapped_index' => 0], $result->all()[1]->params());
        static::assertSame('The paid leave periods must not overlap.', $result->all()[1]->message());
    }

    public function testTheOverlappedIndexIsTheFirstOverlappingPeriod(): void
    {
        $content = DeclarationSamples::build(self::income(null, [
            DeclarationSamples::leave('2026-10-01', '2026-10-02'),
            DeclarationSamples::leave('2026-10-05', '2026-10-10'),
            DeclarationSamples::leave('2026-10-08', '2026-10-09'),
        ]), null, null, null, null);
        $result = (new PaidLeavePeriodsDoNotOverlap())->validate($content);

        static::assertSame(['error hourlyIncome.paidLeavePeriods[2] paid_leave_period.overlap'], DomainAssertions::summary($result));
        static::assertSame(['overlapped_index' => 1], $result->all()[0]->params());
    }

    public function testNoOverlapWithoutPeriods(): void
    {
        static::assertSame([], DomainAssertions::summary((new PaidLeavePeriodsDoNotOverlap())->validate(DeclarationSamples::content())));
        static::assertSame([], DomainAssertions::summary((new PaidLeavePeriodsDoNotOverlap())->validate(DeclarationSamples::contentWithoutIncome())));
    }
}
