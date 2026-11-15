<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Coefficient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\QuantityEncoding;

final class QuantityEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function halfDays(): iterable
    {
        yield 'zero' => [0, '0'];
        yield 'half a day' => [1, '0.5'];
        yield 'one day' => [2, '1'];
        yield 'two days' => [4, '2'];
        yield 'two and a half days' => [5, '2.5'];
        yield 'thirty-two days' => [64, '32'];
    }

    #[DataProvider('halfDays')]
    public function testItEncodesHalfDaysInShortestForm(int $halfDays, string $literal): void
    {
        static::assertSame($literal, QuantityEncoding::encodeHalfDays(HalfDays::fromHalfDays($halfDays))->literal());
        static::assertSame($literal, JsonEncoder::encode(QuantityEncoding::encodeHalfDays(HalfDays::fromHalfDays($halfDays))));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function coefficients(): iterable
    {
        yield 'integer' => ['2', '2'];
        yield 'one decimal' => ['2.5', '2.5'];
        yield 'trailing zeros dropped' => ['2.50', '2.5'];
        yield 'four decimals' => ['0.0001', '0.0001'];
        yield 'zero' => ['0', '0'];
    }

    #[DataProvider('coefficients')]
    public function testItEncodesCoefficientsInShortestForm(string $value, string $literal): void
    {
        static::assertSame($literal, QuantityEncoding::encodeCoefficient(Coefficient::fromDecimalString($value))->literal());
    }
}
