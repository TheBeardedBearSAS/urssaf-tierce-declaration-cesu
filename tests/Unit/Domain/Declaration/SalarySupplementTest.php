<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementSubCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class SalarySupplementTest extends TestCase
{
    private static function make(string $amount, ?string $hourlyWage, ?int $hours): SalarySupplement
    {
        return self::makePair($amount, $hourlyWage, $hours, SupplementCategory::SpecificHours, SupplementSubCategory::NightPresence);
    }

    private static function makePair(
        string $amount,
        ?string $hourlyWage,
        ?int $hours,
        SupplementCategory $category,
        SupplementSubCategory $subCategory,
    ): SalarySupplement {
        return SalarySupplement::of(
            $category,
            $subCategory,
            Money::fromDecimalString($amount),
            $hourlyWage === null ? null : Money::fromDecimalString($hourlyWage),
            $hours,
        );
    }

    public function testKeepsItsParts(): void
    {
        $supplement = DeclarationSamples::supplement();

        static::assertSame(SupplementCategory::SpecificHours, $supplement->category());
        static::assertSame(SupplementSubCategory::NightPresence, $supplement->subCategory());
        static::assertSame(2009, $supplement->amount()->cents());
        static::assertSame(1250, $supplement->hourlyWage()?->cents());
        static::assertSame(3, $supplement->hours());
    }

    public function testHourlyWageAndHoursAreOptional(): void
    {
        $supplement = self::make('20.09', null, null);

        static::assertNull($supplement->hourlyWage());
        static::assertNull($supplement->hours());
    }

    public function testSubCategoryMustBelongToTheCategory(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'salary_supplement.unknown_pair',
            static fn(): SalarySupplement => self::makePair('1', null, null, SupplementCategory::Bonus, SupplementSubCategory::NightPresence),
        );

        static::assertSame('The salary supplement sub-category does not belong to the category.', $exception->getMessage());
    }

    public function testAmountBounds(): void
    {
        static::assertSame(99_999_99, self::make('99999.99', null, null)->amount()->cents());
        $exception = DomainAssertions::assertViolationCode(
            'supplement_amount.out_of_range',
            static fn(): SalarySupplement => self::make('100000', null, null),
        );

        static::assertSame(['min' => '0.00', 'max' => '99999.99'], $exception->params());
    }

    public function testHourlyWageBounds(): void
    {
        static::assertSame(99_999, self::make('1', '999.99', null)->hourlyWage()?->cents());
        $exception = DomainAssertions::assertViolationCode(
            'hourly_wage.out_of_range',
            static fn(): SalarySupplement => self::make('1', '1000', null),
        );

        static::assertSame(['min' => '0.00', 'max' => '999.99'], $exception->params());
    }

    /**
     * @return iterable<string, array{int, string|null}>
     */
    public static function provideHours(): iterable
    {
        yield 'below' => [-1, 'supplement_hours.out_of_range'];
        yield 'zero' => [0, null];
        yield 'maximum' => [999, null];
        yield 'above' => [1000, 'supplement_hours.out_of_range'];
    }

    #[DataProvider('provideHours')]
    public function testHoursBounds(int $hours, ?string $violationCode): void
    {
        if ($violationCode === null) {
            static::assertSame($hours, self::make('1', null, $hours)->hours());

            return;
        }
        $exception = DomainAssertions::assertViolationCode(
            $violationCode,
            static fn(): SalarySupplement => self::make('1', null, $hours),
        );

        static::assertSame(['min' => 0, 'max' => 999], $exception->params());
    }

    public function testChecksRunInOrder(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'salary_supplement.unknown_pair',
            static fn(): SalarySupplement => self::makePair('100000', '1000', 1000, SupplementCategory::Bonus, SupplementSubCategory::NightPresence),
        );
        static::assertNotSame('', $exception->getMessage());
        $exception = DomainAssertions::assertViolationCode(
            'supplement_amount.out_of_range',
            static fn(): SalarySupplement => self::make('100000', '1000', 1000),
        );
        static::assertNotSame('', $exception->getMessage());
        $exception = DomainAssertions::assertViolationCode(
            'hourly_wage.out_of_range',
            static fn(): SalarySupplement => self::make('1', '1000', 1000),
        );
        static::assertNotSame('', $exception->getMessage());
    }
}
