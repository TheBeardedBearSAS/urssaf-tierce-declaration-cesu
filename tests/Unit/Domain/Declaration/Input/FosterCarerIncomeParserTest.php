<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\FosterCarerIncomeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\FosterCarerIncomeParser;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class FosterCarerIncomeParserTest extends TestCase
{
    /**
     * @return array{FosterCarerIncome|null, list<string>}
     */
    private static function parse(?FosterCarerIncomeDraft $draft): array
    {
        $collector = new ViolationCollector();
        $income = (new FosterCarerIncomeParser())->parse($draft, $collector);

        return [$income, DomainAssertions::summary($collector->result())];
    }

    public function testNoDraftIsNoIncome(): void
    {
        static::assertSame([null, []], self::parse(null));
    }

    public function testAValidDraftIsParsed(): void
    {
        [$income, $summary] = self::parse(DraftFixtures::fosterIncome());

        static::assertSame([], $summary);
        static::assertNotNull($income);
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
        [$income, $summary] = self::parse(new FosterCarerIncomeDraft(null, '', null, null, null, null, null, null, null));

        static::assertSame([], $summary);
        static::assertNotNull($income);
        static::assertNull($income->housingAllowance());
        static::assertNull($income->maintenanceAllowanceDays());
        static::assertNull($income->guaranteedMinimumPerMaintenanceDay());
    }

    public function testBoundaries(): void
    {
        [$income, $summary] = self::parse(new FosterCarerIncomeDraft('0', '99', '99', '99', '99', '5', '0', '0', '0'));

        static::assertSame([], $summary);
        static::assertNotNull($income);
        static::assertSame(198, $income->maintenanceAllowanceDays()?->halfDays());
        static::assertSame(50_000, $income->guaranteedMinimumPerMaintenanceDay()?->scaled());
        [$income, $summary] = self::parse(new FosterCarerIncomeDraft(null, null, null, null, null, '2', null, null, null));
        static::assertSame([], $summary);
        static::assertNotNull($income);
        static::assertSame(20_000, $income->guaranteedMinimumPerMaintenanceDay()?->scaled());
    }

    /**
     * @return iterable<string, array{FosterCarerIncomeDraft, list<string>}>
     */
    public static function provideInvalidDrafts(): iterable
    {
        yield 'housing allowance' => [new FosterCarerIncomeDraft('x', null, null, null, null, null, null, null, null), ['error housingAllowance money.invalid_format']];
        yield 'negative housing allowance' => [new FosterCarerIncomeDraft('-1', null, null, null, null, null, null, null, null), ['error housingAllowance money.negative']];
        yield 'maintenance days' => [new FosterCarerIncomeDraft(null, '99.5', null, null, null, null, null, null, null), ['error maintenanceAllowanceDays maintenance_allowance_days.out_of_range']];
        yield 'particular constraint days' => [new FosterCarerIncomeDraft(null, null, '99.5', null, null, null, null, null, null), ['error particularConstraintDays particular_constraint_days.out_of_range']];
        yield 'full time days' => [new FosterCarerIncomeDraft(null, null, null, '99.5', null, null, null, null, null), ['error fullTimeReceptionDays full_time_reception_days.out_of_range']];
        yield 'part time days' => [new FosterCarerIncomeDraft(null, null, null, null, '99.5', null, null, null, null), ['error partTimeReceptionDays part_time_reception_days.out_of_range']];
        yield 'days multiple' => [new FosterCarerIncomeDraft(null, '2.3', null, null, null, null, null, null, null), ['error maintenanceAllowanceDays half_days.not_multiple_of']];
        yield 'guaranteed below' => [new FosterCarerIncomeDraft(null, null, null, null, null, '1.9999', null, null, null), ['error guaranteedMinimumPerMaintenanceDay guaranteed_minimum_per_day.out_of_range']];
        yield 'guaranteed above' => [new FosterCarerIncomeDraft(null, null, null, null, null, '5.0001', null, null, null), ['error guaranteedMinimumPerMaintenanceDay guaranteed_minimum_per_day.out_of_range']];
        yield 'smic constraints' => [new FosterCarerIncomeDraft(null, null, null, null, null, null, '-1', null, null), ['error smicPerParticularConstraintDay coefficient.negative']];
        yield 'smic full time' => [new FosterCarerIncomeDraft(null, null, null, null, null, null, null, 'x', null), ['error smicPerFullTimeReceptionDay coefficient.invalid_format']];
        yield 'smic part time' => [new FosterCarerIncomeDraft(null, null, null, null, null, null, null, null, '0.00001'), ['error smicPerPartTimeReceptionDay coefficient.too_many_decimals']];
        yield 'several' => [
            new FosterCarerIncomeDraft('x', '100', null, null, null, '9', null, null, null),
            ['error housingAllowance money.invalid_format', 'error maintenanceAllowanceDays maintenance_allowance_days.out_of_range', 'error guaranteedMinimumPerMaintenanceDay guaranteed_minimum_per_day.out_of_range'],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidDrafts')]
    public function testInvalidDraftsReportEveryViolationAndYieldNothing(FosterCarerIncomeDraft $draft, array $expected): void
    {
        [$income, $summary] = self::parse($draft);

        static::assertNull($income);
        static::assertSame($expected, $summary);
    }
}
