<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class FosterCarerIncomeTest extends TestCase
{
    public function testKeepsItsNineFields(): void
    {
        $income = DeclarationSamples::fosterIncome();

        static::assertSame(10_000, $income->housingAllowance()?->cents());
        static::assertSame(20, $income->maintenanceAllowanceDays()?->halfDays());
        static::assertSame(5, $income->particularConstraintDays()?->halfDays());
        static::assertSame(6, $income->fullTimeReceptionDays()?->halfDays());
        static::assertSame(8, $income->partTimeReceptionDays()?->halfDays());
        static::assertSame(25_000, $income->guaranteedMinimumPerMaintenanceDay()?->scaled());
        static::assertSame(15_000, $income->smicPerParticularConstraintDay()?->scaled());
        static::assertSame(7_500, $income->smicPerFullTimeReceptionDay()?->scaled());
        static::assertSame(2_500, $income->smicPerPartTimeReceptionDay()?->scaled());
    }

    public function testEveryFieldIsOptional(): void
    {
        $income = new FosterCarerIncome(null, null, null, null, null, null, null, null, null);

        static::assertNull($income->housingAllowance());
        static::assertNull($income->maintenanceAllowanceDays());
        static::assertNull($income->particularConstraintDays());
        static::assertNull($income->fullTimeReceptionDays());
        static::assertNull($income->partTimeReceptionDays());
        static::assertNull($income->guaranteedMinimumPerMaintenanceDay());
        static::assertNull($income->smicPerParticularConstraintDay());
        static::assertNull($income->smicPerFullTimeReceptionDay());
        static::assertNull($income->smicPerPartTimeReceptionDay());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideDayFields(): iterable
    {
        yield 'maintenance allowance days' => [0, 'maintenance_allowance_days'];
        yield 'particular constraint days' => [1, 'particular_constraint_days'];
        yield 'full time reception days' => [2, 'full_time_reception_days'];
        yield 'part time reception days' => [3, 'part_time_reception_days'];
    }

    /**
     * @param array<int, HalfDays|null> $days
     */
    private static function withDays(array $days): FosterCarerIncome
    {
        return new FosterCarerIncome(null, $days[0] ?? null, $days[1] ?? null, $days[2] ?? null, $days[3] ?? null, null, null, null, null);
    }

    #[DataProvider('provideDayFields')]
    public function testDayCountsAreAtMostNinetyNine(int $position, string $subject): void
    {
        $accepted = self::withDays([$position => DeclarationSamples::days('99')]);
        static::assertSame(198, [$accepted->maintenanceAllowanceDays(), $accepted->particularConstraintDays(), $accepted->fullTimeReceptionDays(), $accepted->partTimeReceptionDays()][$position]?->halfDays());

        $exception = DomainAssertions::assertViolationCode(
            $subject . '.out_of_range',
            static fn(): FosterCarerIncome => self::withDays([$position => DeclarationSamples::days('99.5')]),
        );

        static::assertSame(['min' => '0', 'max' => '99'], $exception->params());
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideGuaranteedMinimums(): iterable
    {
        yield 'below' => ['1.9999', 'guaranteed_minimum_per_day.out_of_range'];
        yield 'minimum' => ['2', null];
        yield 'maximum' => ['5', null];
        yield 'above' => ['5.0001', 'guaranteed_minimum_per_day.out_of_range'];
    }

    private static function withGuaranteedMinimum(string $value): FosterCarerIncome
    {
        return new FosterCarerIncome(null, null, null, null, null, DeclarationSamples::coefficient($value), null, null, null);
    }

    #[DataProvider('provideGuaranteedMinimums')]
    public function testGuaranteedMinimumIsBetweenTwoAndFive(string $value, ?string $violationCode): void
    {
        if ($violationCode === null) {
            static::assertSame($value, self::withGuaranteedMinimum($value)->guaranteedMinimumPerMaintenanceDay()?->toDecimalString());

            return;
        }
        $exception = DomainAssertions::assertViolationCode(
            $violationCode,
            static fn(): FosterCarerIncome => self::withGuaranteedMinimum($value),
        );

        static::assertSame(['min' => '2', 'max' => '5'], $exception->params());
    }

    public function testSmicCoefficientsHaveNoUpperBoundLocally(): void
    {
        $income = new FosterCarerIncome(null, null, null, null, null, null, DeclarationSamples::coefficient('99'), DeclarationSamples::coefficient('99'), DeclarationSamples::coefficient('99'));

        static::assertSame(990_000, $income->smicPerParticularConstraintDay()?->scaled());
    }
}
