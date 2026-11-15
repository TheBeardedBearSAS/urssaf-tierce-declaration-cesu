<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Coefficient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class CoefficientTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function provideValidValues(): iterable
    {
        yield 'integer' => ['2', 20_000, '2'];
        yield 'one decimal' => ['2.5', 25_000, '2.5'];
        yield 'four decimals' => ['0.0001', 1, '0.0001'];
        yield 'trailing zeros dropped' => ['1.5000', 15_000, '1.5'];
        yield 'zero' => ['0', 0, '0'];
        yield 'nine integer digits' => ['999999999', 9_999_999_990_000, '999999999'];
    }

    #[DataProvider('provideValidValues')]
    public function testValidValues(string $raw, int $scaled, string $string): void
    {
        $coefficient = Coefficient::fromDecimalString($raw);

        static::assertSame($scaled, $coefficient->scaled());
        static::assertSame($string, $coefficient->toDecimalString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'five decimals' => ['0.00001', 'coefficient.too_many_decimals'];
        yield 'negative' => ['-1', 'coefficient.negative'];
        yield 'comma' => ['1,5', 'coefficient.invalid_format'];
        yield 'empty' => ['', 'coefficient.invalid_format'];
        yield 'ten integer digits' => ['1000000000', 'coefficient.too_large'];
    }

    #[DataProvider('provideInvalidValues')]
    public function testInvalidValues(string $raw, string $code): void
    {
        $exception = DomainAssertions::assertViolationCode($code, static fn(): Coefficient => Coefficient::fromDecimalString($raw));
        static::assertNotSame('', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideRangeChecks(): iterable
    {
        yield 'below the minimum' => ['1.9999', 'guaranteed_minimum_per_day.out_of_range'];
        yield 'minimum' => ['2', null];
        yield 'inside' => ['3.5', null];
        yield 'maximum' => ['5', null];
        yield 'above the maximum' => ['5.0001', 'guaranteed_minimum_per_day.out_of_range'];
    }

    #[DataProvider('provideRangeChecks')]
    public function testAssertBetween(string $raw, ?string $violationCode): void
    {
        $coefficient = Coefficient::fromDecimalString($raw);
        $min = Coefficient::fromDecimalString('2');
        $max = Coefficient::fromDecimalString('5');

        if ($violationCode === null) {
            static::assertSame($coefficient, $coefficient->assertBetween($min, $max, 'guaranteed_minimum_per_day'));

            return;
        }
        $exception = DomainAssertions::assertViolationCode(
            $violationCode,
            static fn(): Coefficient => $coefficient->assertBetween($min, $max, 'guaranteed_minimum_per_day'),
        );
        static::assertSame(['min' => '2', 'max' => '5'], $exception->params());
    }

    public function testEquality(): void
    {
        static::assertTrue(Coefficient::fromDecimalString('1.5')->equals(Coefficient::fromDecimalString('1.50')));
        static::assertFalse(Coefficient::fromDecimalString('1.5')->equals(Coefficient::fromDecimalString('1.6')));
    }
}
