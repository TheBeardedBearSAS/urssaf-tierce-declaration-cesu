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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\HourlyWorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\IncomeIsPresent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\WorkedHoursRules;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Severity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class IncomeAndHoursSpecificationsTest extends TestCase
{
    public function testMissingIncomeIsAWarningAtTheRoot(): void
    {
        $result = (new IncomeIsPresent())->validate(DeclarationSamples::contentWithoutIncome());

        static::assertSame(['warning  declaration.income_missing'], DomainAssertions::summary($result));
        static::assertTrue($result->isValid());
        $violation = $result->all()[0];
        static::assertSame(Severity::Warning, $violation->severity());
        static::assertSame('No income is given: URSSAF requires hours or an income element.', $violation->message());
        static::assertSame([], $violation->params());
    }

    public function testAnHourlyIncomeIsEnough(): void
    {
        static::assertSame([], DomainAssertions::summary((new IncomeIsPresent())->validate(DeclarationSamples::content())));
    }

    public function testAFosterCarerIncomeIsEnough(): void
    {
        static::assertSame([], DomainAssertions::summary((new IncomeIsPresent())->validate(DeclarationSamples::contentWithFosterIncome())));
    }

    /**
     * @return iterable<string, array{int, int|null, int|null, list<string>}>
     */
    public static function provideHours(): iterable
    {
        yield 'no overtime, no hours' => [0, null, null, []];
        yield 'no overtime, few hours' => [39, null, null, []];
        yield 'zero overtime, few hours' => [39, 0, 0, []];
        yield 'first tier with forty hours' => [40, 1, null, []];
        yield 'first tier with thirty-nine hours' => [39, 1, null, ['error workedHours worked_hours.overtime_requires_forty_normal_hours']];
        yield 'first tier zero second tier one' => [
            39,
            0,
            1,
            [
                'error workedHours worked_hours.overtime_requires_forty_normal_hours',
                'error secondTierOvertimeHours worked_hours.second_tier_requires_eight_first_tier_hours',
            ],
        ];
        yield 'second tier with absent first tier' => [40, null, 1, ['error secondTierOvertimeHours worked_hours.second_tier_requires_eight_first_tier_hours']];
        yield 'second tier with seven first tier hours' => [40, 7, 1, ['error secondTierOvertimeHours worked_hours.second_tier_requires_eight_first_tier_hours']];
        yield 'second tier with eight first tier hours' => [40, 8, 1, []];
        yield 'second tier with too few normal hours' => [39, 8, 1, ['error workedHours worked_hours.overtime_requires_forty_normal_hours']];
        yield 'second tier only with forty hours and eight first tier' => [40, 8, 40, []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideHours')]
    public function testOvertimeThresholds(int $worked, ?int $first, ?int $second, array $expected): void
    {
        $result = (new WorkedHoursRules())->validate(WorkedHours::of($worked, $first, $second));

        static::assertSame($expected, DomainAssertions::summary($result));
    }

    public function testThresholdParameters(): void
    {
        $violations = (new WorkedHoursRules())->validate(WorkedHours::of(1, 0, 1))->all();

        static::assertCount(2, $violations);
        static::assertSame(Severity::Error, $violations[0]->severity());
        static::assertSame('Overtime requires at least 40 normal hours.', $violations[0]->message());
        static::assertSame(['min_worked_hours' => 40], $violations[0]->params());
        static::assertSame('Second-tier overtime requires at least 8 first-tier overtime hours.', $violations[1]->message());
        static::assertSame(['min_first_tier_overtime_hours' => 8], $violations[1]->params());
    }

    public function testHourlyWorkedHoursNestsTheViolationsUnderTheIncome(): void
    {
        $income = HourlyIncome::of(Money::fromCents(1000), WorkedHours::of(10, 0, 1), null, [], []);
        $result = (new HourlyWorkedHours())->validate(DeclarationSamples::build($income, null, null, null, null));

        static::assertSame(
            [
                'error hourlyIncome.workedHours worked_hours.overtime_requires_forty_normal_hours',
                'error hourlyIncome.secondTierOvertimeHours worked_hours.second_tier_requires_eight_first_tier_hours',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testHourlyWorkedHoursIgnoresAMissingHourlyIncome(): void
    {
        static::assertSame([], DomainAssertions::summary((new HourlyWorkedHours())->validate(DeclarationSamples::contentWithoutIncome())));
    }

    public function testHourlyWorkedHoursAcceptsValidHours(): void
    {
        static::assertSame([], DomainAssertions::summary((new HourlyWorkedHours())->validate(DeclarationSamples::content())));
    }
}
