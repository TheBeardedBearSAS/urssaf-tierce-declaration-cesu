<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\PaidLeavePeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

/**
 * One method per rule of the period.
 *
 * @mago-expect lint:too-many-methods
 */
final class PaidLeavePeriodTest extends TestCase
{
    public function testAValidPeriodKeepsItsParts(): void
    {
        $period = DeclarationSamples::leave('2026-10-05', '2026-10-09', '2.5');

        static::assertSame('2026-10-05', $period->start()->toString());
        static::assertSame('2026-10-09', $period->end()->toString());
        static::assertSame('2.5', $period->days()->toDecimalString());
    }

    public function testAOneDayPeriodIsAccepted(): void
    {
        static::assertSame('1', DeclarationSamples::leave('2026-10-05', '2026-10-05', '1')->days()->toDecimalString());
    }

    public function testEndBeforeStartIsRefused(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'paid_leave_period.end_before_start',
            static fn(): PaidLeavePeriod => DeclarationSamples::leave('2026-10-06', '2026-10-05'),
        );

        static::assertSame('Invalid paid_leave_period: the end must not be before the start.', $exception->getMessage());
    }

    public function testThirtyOneDaysAreAccepted(): void
    {
        static::assertSame('31', DeclarationSamples::leave('2026-10-01', '2026-10-31', '31')->days()->toDecimalString());
    }

    public function testMoreThanThirtyOneDaysAreRefused(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'paid_leave_days.out_of_range',
            static fn(): PaidLeavePeriod => DeclarationSamples::leave('2026-10-01', '2026-10-31', '31.5'),
        );

        static::assertSame(['min' => '0', 'max' => '31'], $exception->params());
    }

    public function testZeroDaysAreAccepted(): void
    {
        static::assertSame('0', DeclarationSamples::leave('2026-10-01', '2026-10-02', '0')->days()->toDecimalString());
    }

    /**
     * @return iterable<string, array{string, string, string, string, bool}>
     */
    public static function provideOverlaps(): iterable
    {
        yield 'identical' => ['2026-10-05', '2026-10-10', '2026-10-05', '2026-10-10', true];
        yield 'other inside' => ['2026-10-05', '2026-10-20', '2026-10-08', '2026-10-09', true];
        yield 'this inside the other' => ['2026-10-08', '2026-10-09', '2026-10-05', '2026-10-20', true];
        yield 'other ends on the start' => ['2026-10-05', '2026-10-10', '2026-10-01', '2026-10-05', true];
        yield 'other starts on the end' => ['2026-10-05', '2026-10-10', '2026-10-10', '2026-10-15', true];
        yield 'other ends the day before' => ['2026-10-05', '2026-10-10', '2026-10-01', '2026-10-04', false];
        yield 'other starts the day after' => ['2026-10-05', '2026-10-10', '2026-10-11', '2026-10-15', false];
    }

    #[DataProvider('provideOverlaps')]
    public function testOverlapsIncludesBothBoundsAndIsSymmetric(string $s1, string $e1, string $s2, string $e2, bool $expected): void
    {
        $first = DeclarationSamples::leave($s1, $e1);
        $second = DeclarationSamples::leave($s2, $e2);

        static::assertSame($expected, $first->overlaps($second));
        static::assertSame($expected, $second->overlaps($first));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideWithinChecks(): iterable
    {
        yield 'inside' => ['2026-10-05', '2026-10-09', true];
        yield 'the whole period' => ['2026-10-01', '2026-10-31', true];
        yield 'starts before' => ['2026-09-30', '2026-10-09', false];
        yield 'ends after' => ['2026-10-05', '2026-11-01', false];
        yield 'entirely before' => ['2026-09-01', '2026-09-02', false];
        yield 'entirely after' => ['2026-11-01', '2026-11-02', false];
    }

    #[DataProvider('provideWithinChecks')]
    public function testIsWithin(string $start, string $end, bool $expected): void
    {
        static::assertSame($expected, DeclarationSamples::leave($start, $end)->isWithin(DeclarationSamples::period()));
    }

    public function testEqualityComparesEveryField(): void
    {
        $period = DeclarationSamples::leave('2026-10-05', '2026-10-09', '2');

        static::assertTrue($period->equals(DeclarationSamples::leave('2026-10-05', '2026-10-09', '2')));
        static::assertFalse($period->equals(DeclarationSamples::leave('2026-10-04', '2026-10-09', '2')));
        static::assertFalse($period->equals(DeclarationSamples::leave('2026-10-05', '2026-10-10', '2')));
        static::assertFalse($period->equals(PaidLeavePeriod::of(CalendarDate::fromString('2026-10-05'), CalendarDate::fromString('2026-10-09'), HalfDays::fromHalfDays(5))));
    }
}
