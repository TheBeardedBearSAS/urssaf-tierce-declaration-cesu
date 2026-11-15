<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\MoneyEncoding;

final class MoneyEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function moneyCents(): iterable
    {
        yield 'zero' => [0, '0.00'];
        yield 'one cent' => [1, '0.01'];
        yield 'ten cents' => [10, '0.10'];
        yield 'one euro' => [100, '1.00'];
        yield 'ten euros' => [1000, '10.00'];
        yield 'declared net salary' => [6310, '63.10'];
        yield 'declared contribution' => [2009, '20.09'];
        yield 'ninety-nine euros' => [9900, '99.00'];
        yield 'ninety-nine cents' => [99, '0.99'];
        yield 'ninety-nine thousand' => [99_999, '999.99'];
        yield 'ceiling' => [9_999_999, '99999.99'];
        yield 'big' => [999_999_999, '9999999.99'];
    }

    #[DataProvider('moneyCents')]
    public function testItEncodesMoneyWithTwoDecimalsAndNoFloatDrift(int $cents, string $literal): void
    {
        static::assertSame($literal, MoneyEncoding::encode(Money::fromCents($cents))->literal());
        static::assertSame($literal, JsonEncoder::encode(MoneyEncoding::encode(Money::fromCents($cents))));
    }

    #[DataProvider('moneyCents')]
    public function testItRoundTripsThroughJson(int $cents): void
    {
        $json = JsonEncoder::encode(['montant' => MoneyEncoding::encode(Money::fromCents($cents))]);
        $number = JsonDecoder::decode($json)->requireNumber('montant');

        static::assertSame($cents, MoneyEncoding::decode($number)->cents());
    }

    /**
     * @return iterable<string, array{int|float, int}>
     */
    public static function decodableAmounts(): iterable
    {
        yield 'float 63.1' => [63.1, 6310];
        yield 'float 20.09' => [20.09, 2009];
        yield 'float zero' => [0.0, 0];
        yield 'float 0.1 + 0.2' => [0.1 + 0.2, 30];
        yield 'float half up' => [0.125, 13];
        yield 'float below half' => [0.124, 12];
        yield 'integer' => [1200, 120_000];
        yield 'integer zero' => [0, 0];
        yield 'float one decimal' => [0.5, 50];
    }

    #[DataProvider('decodableAmounts')]
    public function testItDecodesAmountsWithoutFloatDrift(int|float $wire, int $expectedCents): void
    {
        static::assertSame($expectedCents, MoneyEncoding::decode($wire)->cents());
    }

    /**
     * @return iterable<string, array{int|float}>
     */
    public static function undecodableAmounts(): iterable
    {
        yield 'negative integer' => [-1];
        yield 'negative float' => [-0.5];
        yield 'too large integer' => [PHP_INT_MAX];
        yield 'too large float' => [1.0e20];
    }

    #[DataProvider('undecodableAmounts')]
    public function testItRejectsInvalidAmounts(int|float $wire): void
    {
        try {
            MoneyEncoding::decode($wire);
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame(
                'Unexpected URSSAF response: amount is not a valid non-negative amount in euros.',
                $exception->getMessage(),
            );
            static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function nonFiniteAmounts(): iterable
    {
        yield 'infinite' => [INF];
        yield 'negative infinite' => [-INF];
        yield 'not a number' => [NAN];
    }

    #[DataProvider('nonFiniteAmounts')]
    public function testItNamesNonFiniteAmounts(float $wire): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('Unexpected URSSAF response: amount is not a finite number.');

        MoneyEncoding::decode($wire);
    }
}
