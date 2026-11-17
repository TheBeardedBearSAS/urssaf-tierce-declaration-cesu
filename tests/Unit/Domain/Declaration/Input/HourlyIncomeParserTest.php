<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\HourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\HourlyIncomeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\HourlyIncomeParser;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\PaidLeavePeriodDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\SalarySupplementDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\PaidLeavePeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class HourlyIncomeParserTest extends TestCase
{
    /**
     * @param list<SalarySupplementDraft> $supplements
     * @param list<PaidLeavePeriodDraft>  $periods
     */
    private static function draft(
        ?string $wage = '12.5',
        ?int $worked = 100,
        array $supplements = [],
        array $periods = [],
    ): HourlyIncomeDraft {
        return new HourlyIncomeDraft($wage, $worked, null, null, null, $supplements, $periods);
    }

    /**
     * @return array{HourlyIncome|null, list<string>}
     */
    private static function parse(?HourlyIncomeDraft $draft): array
    {
        $collector = new ViolationCollector();
        $income = (new HourlyIncomeParser())->parse($draft, $collector);

        return [$income, DomainAssertions::summary($collector->result())];
    }

    public function testNoDraftIsNoIncome(): void
    {
        static::assertSame([null, []], self::parse(null));
    }

    public function testAValidDraftIsParsed(): void
    {
        [$income, $summary] = self::parse(self::draft(
            '999.99',
            200,
            [new SalarySupplementDraft('FINC', 'LICE', '99999.99', '999.99', 999)],
            [new PaidLeavePeriodDraft('2026-10-05', '2026-10-06', '31')],
        ));

        static::assertSame([], $summary);
        static::assertNotNull($income);
        static::assertSame(99_999, $income->hourlyWage()->cents());
        static::assertSame(200, $income->hours()->workedHours());
        static::assertNull($income->hours()->firstTierOvertimeHours());
        static::assertNull($income->paidLeaveHours());
        static::assertCount(1, $income->supplements());
        static::assertCount(1, $income->paidLeavePeriods());
    }

    public function testBoundariesOfTheHours(): void
    {
        [$income, $summary] = self::parse(new HourlyIncomeDraft('0', 0, 0, 0, 0));

        static::assertSame([], $summary);
        static::assertNotNull($income);
        static::assertSame([0, 0, 0, 0], [$income->hours()->workedHours(), $income->hours()->firstTierOvertimeHours(), $income->hours()->secondTierOvertimeHours(), $income->paidLeaveHours()]);
        [$income, $summary] = self::parse(new HourlyIncomeDraft('1', 200, 35, 40, 200));
        static::assertSame([], $summary);
        static::assertNotNull($income);
        static::assertSame([200, 35, 40, 200], [$income->hours()->workedHours(), $income->hours()->firstTierOvertimeHours(), $income->hours()->secondTierOvertimeHours(), $income->paidLeaveHours()]);
    }

    /**
     * @return iterable<string, array{HourlyIncomeDraft, list<string>}>
     */
    public static function provideInvalidDrafts(): iterable
    {
        yield 'missing wage and hours' => [self::draft(null, null), ['error hourlyWage hourly_wage.required', 'error workedHours worked_hours.required']];
        yield 'blank wage' => [self::draft(''), ['error hourlyWage hourly_wage.required']];
        yield 'wage above the maximum' => [self::draft('1000'), ['error hourlyWage hourly_wage.out_of_range']];
        yield 'wage with a comma' => [self::draft('12,5'), ['error hourlyWage money.invalid_format']];
        yield 'worked hours below' => [self::draft('12', -1), ['error workedHours worked_hours.out_of_range']];
        yield 'worked hours above' => [self::draft('12', 201), ['error workedHours worked_hours.out_of_range']];
        yield 'first tier below' => [new HourlyIncomeDraft('12', 100, -1, null, null), ['error firstTierOvertimeHours first_tier_overtime_hours.out_of_range']];
        yield 'first tier above' => [new HourlyIncomeDraft('12', 100, 36, null, null), ['error firstTierOvertimeHours first_tier_overtime_hours.out_of_range']];
        yield 'second tier below' => [new HourlyIncomeDraft('12', 100, null, -1, null), ['error secondTierOvertimeHours second_tier_overtime_hours.out_of_range']];
        yield 'second tier above' => [new HourlyIncomeDraft('12', 100, null, 41, null), ['error secondTierOvertimeHours second_tier_overtime_hours.out_of_range']];
        yield 'paid leave hours below' => [new HourlyIncomeDraft('12', 100, null, null, -1), ['error paidLeaveHours paid_leave_hours.out_of_range']];
        yield 'paid leave hours above' => [new HourlyIncomeDraft('12', 100, null, null, 201), ['error paidLeaveHours paid_leave_hours.out_of_range']];
    }

    /**
     * @return iterable<string, array{HourlyIncomeDraft, list<string>}>
     */
    public static function provideInvalidSupplementsAndPeriods(): iterable
    {
        yield 'supplement without anything' => [
            self::draft('12', 100, [new SalarySupplementDraft('HEUR', 'NUIT', '1', null, null), new SalarySupplementDraft(null, null, null, null, null)]),
            [
                'error supplements[1].category supplement_category.required',
                'error supplements[1].subCategory supplement_sub_category.required',
                'error supplements[1].amount supplement_amount.required',
            ],
        ];
        yield 'supplement unknown codes' => [
            self::draft('12', 100, [new SalarySupplementDraft('XXXX', 'YYYY', '1', null, null)]),
            ['error supplements[0].category supplement_category.unknown', 'error supplements[0].subCategory supplement_sub_category.unknown'],
        ];
        yield 'supplement amount above' => [
            self::draft('12', 100, [new SalarySupplementDraft('HEUR', 'NUIT', '100000', null, null)]),
            ['error supplements[0].amount supplement_amount.out_of_range'],
        ];
        yield 'supplement hourly wage above' => [
            self::draft('12', 100, [new SalarySupplementDraft('HEUR', 'NUIT', '1', '1000', null)]),
            ['error supplements[0].hourlyWage hourly_wage.out_of_range'],
        ];
        yield 'supplement hours below' => [
            self::draft('12', 100, [new SalarySupplementDraft('HEUR', 'NUIT', '1', null, -1)]),
            ['error supplements[0].hours supplement_hours.out_of_range'],
        ];
        yield 'supplement hours above' => [
            self::draft('12', 100, [new SalarySupplementDraft('HEUR', 'NUIT', '1', null, 1000)]),
            ['error supplements[0].hours supplement_hours.out_of_range'],
        ];
        yield 'supplement pair' => [
            self::draft('12', 100, [new SalarySupplementDraft('HEUR', 'NUIT', '1', null, null), new SalarySupplementDraft('PRIM', 'NUIT', '1', null, null)]),
            ['error supplements[1].subCategory salary_supplement.unknown_pair'],
        ];
        yield 'period without anything' => [
            self::draft('12', 100, [], [new PaidLeavePeriodDraft(null, null, null)]),
            [
                'error paidLeavePeriods[0].startDate start_date.required',
                'error paidLeavePeriods[0].endDate end_date.required',
                'error paidLeavePeriods[0].days paid_leave_days.required',
            ],
        ];
        yield 'period start only invalid' => [
            self::draft('12', 100, [], [new PaidLeavePeriodDraft('x', '2026-10-06', '1')]),
            ['error paidLeavePeriods[0].startDate calendar_date.invalid_format'],
        ];
        yield 'period end only invalid' => [
            self::draft('12', 100, [], [new PaidLeavePeriodDraft('2026-10-05', 'x', '1')]),
            ['error paidLeavePeriods[0].endDate calendar_date.invalid_format'],
        ];
        yield 'period days only invalid' => [
            self::draft('12', 100, [], [new PaidLeavePeriodDraft('2026-10-05', '2026-10-06', 'x')]),
            ['error paidLeavePeriods[0].days half_days.invalid_format'],
        ];
        yield 'period days' => [
            self::draft('12', 100, [], [new PaidLeavePeriodDraft('2026-10-05', '2026-10-06', '31.5'), new PaidLeavePeriodDraft('2026-10-05', '2026-10-06', '2.3'), new PaidLeavePeriodDraft('2026-10-05', '2026-10-06', '2.25')]),
            [
                'error paidLeavePeriods[0].days paid_leave_days.out_of_range',
                'error paidLeavePeriods[1].days half_days.not_multiple_of',
                'error paidLeavePeriods[2].days half_days.too_many_decimals',
            ],
        ];
        yield 'period end before start' => [
            self::draft('12', 100, [], [new PaidLeavePeriodDraft('2026-10-05', '2026-10-06', '1'), new PaidLeavePeriodDraft('2026-10-06', '2026-10-05', '1')]),
            ['error paidLeavePeriods[1].endDate paid_leave_period.end_before_start'],
        ];
        yield 'period invalid dates' => [
            self::draft('12', 100, [], [new PaidLeavePeriodDraft('x', '2026-02-30', '1')]),
            ['error paidLeavePeriods[0].startDate calendar_date.invalid_format', 'error paidLeavePeriods[0].endDate calendar_date.invalid_date'],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidDrafts')]
    #[DataProvider('provideInvalidSupplementsAndPeriods')]
    public function testInvalidDraftsReportEveryViolationAndYieldNothing(HourlyIncomeDraft $draft, array $expected): void
    {
        [$income, $summary] = self::parse($draft);

        static::assertNull($income);
        static::assertSame($expected, $summary);
    }

    public function testValidSupplementsAndPeriodsKeepTheirOrder(): void
    {
        [$income] = self::parse(self::draft(
            '12',
            100,
            [new SalarySupplementDraft('PRIM', 'PRIM', '1', null, null), new SalarySupplementDraft('HEUR', 'NUIT', '2', null, null)],
            [new PaidLeavePeriodDraft('2026-10-20', '2026-10-21', '1'), new PaidLeavePeriodDraft('2026-10-05', '2026-10-06', '2')],
        ));

        static::assertNotNull($income);
        static::assertSame([100, 200], array_map(static fn(SalarySupplement $supplement): int => $supplement->amount()->cents(), $income->supplements()));
        static::assertSame(['2026-10-20', '2026-10-05'], array_map(static fn(PaidLeavePeriod $period): string => $period->start()->toString(), $income->paidLeavePeriods()));
    }

    public function testASupplementErrorsDoNotLoseTheOtherErrors(): void
    {
        [$income, $summary] = self::parse(new HourlyIncomeDraft('1000', 201, 36, 41, 201, [new SalarySupplementDraft(null, null, null, null, null)], [new PaidLeavePeriodDraft(null, null, null)]));

        static::assertNull($income);
        static::assertSame(
            [
                'error hourlyWage hourly_wage.out_of_range',
                'error workedHours worked_hours.out_of_range',
                'error firstTierOvertimeHours first_tier_overtime_hours.out_of_range',
                'error secondTierOvertimeHours second_tier_overtime_hours.out_of_range',
                'error paidLeaveHours paid_leave_hours.out_of_range',
                'error supplements[0].category supplement_category.required',
                'error supplements[0].subCategory supplement_sub_category.required',
                'error supplements[0].amount supplement_amount.required',
                'error paidLeavePeriods[0].startDate start_date.required',
                'error paidLeavePeriods[0].endDate end_date.required',
                'error paidLeavePeriods[0].days paid_leave_days.required',
            ],
            $summary,
        );
    }
}
