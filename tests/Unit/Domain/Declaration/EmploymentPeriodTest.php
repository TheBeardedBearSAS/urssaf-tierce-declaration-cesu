<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\EmploymentPeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

/**
 * One method per rule of the period.
 *
 * @mago-expect lint:too-many-methods
 */
final class EmploymentPeriodTest extends TestCase
{
    public function testAPeriodInOneMonthIsAccepted(): void
    {
        $period = DeclarationSamples::period('2026-10-01', '2026-10-31');

        static::assertSame('2026-10-01', $period->start()->toString());
        static::assertSame('2026-10-31', $period->end()->toString());
    }

    public function testAOneDayPeriodIsAccepted(): void
    {
        $period = DeclarationSamples::period('2026-10-05', '2026-10-05');

        static::assertTrue($period->start()->equals($period->end()));
    }

    public function testEndBeforeStartIsRefused(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'employment_period.end_before_start',
            static fn(): EmploymentPeriod => DeclarationSamples::period('2026-10-02', '2026-10-01'),
        );

        static::assertSame('Invalid employment_period: the end must not be before the start.', $exception->getMessage());
        static::assertSame([], $exception->params());
    }

    public function testEndBeforeStartWinsOverSeveralMonths(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'employment_period.end_before_start',
            static fn(): EmploymentPeriod => DeclarationSamples::period('2026-11-01', '2026-10-31'),
        );
        static::assertNotSame('', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideSeveralMonths(): iterable
    {
        yield 'next month' => ['2026-10-31', '2026-11-01'];
        yield 'same month number, next year' => ['2025-10-01', '2026-10-31'];
        yield 'same month, after the start day' => ['2026-10-01', '2027-10-01'];
    }

    #[DataProvider('provideSeveralMonths')]
    public function testAPeriodOverSeveralMonthsIsRefused(string $start, string $end): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'employment_period.not_single_month',
            static fn(): EmploymentPeriod => DeclarationSamples::period($start, $end),
        );

        static::assertSame('Invalid employment_period: the period must not span several months.', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideContainedDates(): iterable
    {
        yield 'day before the start' => ['2026-09-30', false];
        yield 'start' => ['2026-10-01', true];
        yield 'inside' => ['2026-10-15', true];
        yield 'end' => ['2026-10-31', true];
        yield 'day after the end' => ['2026-11-01', false];
    }

    #[DataProvider('provideContainedDates')]
    public function testContainsIncludesBothBounds(string $date, bool $expected): void
    {
        static::assertSame($expected, DeclarationSamples::period()->contains(CalendarDate::fromString($date)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideStartComparisons(): iterable
    {
        yield 'start is after the day before' => ['2026-09-30', true];
        yield 'start is not after the same day' => ['2026-10-01', false];
        yield 'start is not after the day after' => ['2026-10-02', false];
    }

    #[DataProvider('provideStartComparisons')]
    public function testStartsAfter(string $date, bool $expected): void
    {
        static::assertSame($expected, DeclarationSamples::period()->startsAfter(CalendarDate::fromString($date)));
    }

    public function testEquality(): void
    {
        $period = DeclarationSamples::period('2026-10-01', '2026-10-31');

        static::assertTrue($period->equals(DeclarationSamples::period('2026-10-01', '2026-10-31')));
        static::assertFalse($period->equals(DeclarationSamples::period('2026-10-02', '2026-10-31')));
        static::assertFalse($period->equals(DeclarationSamples::period('2026-10-01', '2026-10-30')));
    }
}
