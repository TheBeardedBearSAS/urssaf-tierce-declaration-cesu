<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class MoneyTest extends TestCase
{
    public function testFromCents(): void
    {
        static::assertSame(0, Money::fromCents(0)->cents());
        static::assertSame(1, Money::fromCents(1)->cents());
        static::assertSame(PHP_INT_MAX, Money::fromCents(PHP_INT_MAX)->cents());
    }

    public function testFromCentsRejectsNegativeAmounts(): void
    {
        $this->expectExceptionObject(InvalidValue::negative('money'));

        Money::fromCents(-1);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideDecimalStrings(): iterable
    {
        yield 'zero' => ['0', 0];
        yield 'integer' => ['10', 1000];
        yield 'one decimal' => ['63.1', 6310];
        yield 'two decimals' => ['20.09', 2009];
        yield 'trailing zero decimals' => ['0.50', 50];
        yield 'cents only' => ['0.01', 1];
        yield 'contract maximum' => ['999.99', 99_999];
        yield 'maximum integer digits' => ['999999999999999', 99_999_999_999_999_900];
    }

    #[DataProvider('provideDecimalStrings')]
    public function testFromDecimalString(string $amount, int $cents): void
    {
        static::assertSame($cents, Money::fromDecimalString($amount)->cents());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidDecimalStrings(): iterable
    {
        yield 'three decimals' => ['1.001', 'money.too_many_decimals'];
        yield 'comma' => ['1,00', 'money.invalid_format'];
        yield 'leading space' => [' 1', 'money.invalid_format'];
        yield 'plus sign' => ['+1', 'money.invalid_format'];
        yield 'exponent' => ['1e3', 'money.invalid_format'];
        yield 'leading zero' => ['01', 'money.invalid_format'];
        yield 'empty' => ['', 'money.invalid_format'];
        yield 'negative' => ['-1', 'money.negative'];
        yield 'too many integer digits' => ['1000000000000000', 'money.too_large'];
    }

    #[DataProvider('provideInvalidDecimalStrings')]
    public function testFromDecimalStringRejectsInvalidInput(string $amount, string $code): void
    {
        $exception = DomainAssertions::assertViolationCode($code, static fn(): Money => Money::fromDecimalString($amount));

        static::assertSame($code, $exception->violationCode());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideCents(): iterable
    {
        yield 'zero' => [0, '0.00'];
        yield 'one cent' => [1, '0.01'];
        yield 'fifty cents' => [50, '0.50'];
        yield 'one euro' => [100, '1.00'];
        yield 'twelve fifty' => [1250, '12.50'];
        yield 'maximum' => [9_999_999, '99999.99'];
    }

    #[DataProvider('provideCents')]
    public function testToDecimalString(int $cents, string $expected): void
    {
        static::assertSame($expected, Money::fromCents($cents)->toDecimalString());
    }
}
