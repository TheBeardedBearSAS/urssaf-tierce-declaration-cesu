<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Decimal;

final class DecimalFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function provideFormats(): iterable
    {
        yield 'zero with two decimals' => [0, 2, 2, '0.00'];
        yield 'cents' => [5, 2, 2, '0.05'];
        yield 'tenths of euro' => [50, 2, 2, '0.50'];
        yield 'integer part' => [1250, 2, 2, '12.50'];
        yield 'whole euros' => [100, 2, 2, '1.00'];
        yield 'one decimal' => [25, 1, 0, '2.5'];
        yield 'one decimal trimmed' => [20, 1, 0, '2'];
        yield 'zero trimmed' => [0, 1, 0, '0'];
        yield 'two decimals trimmed' => [1750, 3, 0, '1.75'];
        yield 'minimum decimals kept' => [1700, 3, 1, '1.7'];
        yield 'minimum decimals padded' => [1000, 3, 1, '1.0'];
        yield 'leading fraction zeros' => [1005, 3, 0, '1.005'];
        yield 'large' => [99_999_999, 2, 2, '999999.99'];
    }

    #[DataProvider('provideFormats')]
    public function testFormat(int $scaled, int $decimals, int $minDecimals, string $expected): void
    {
        static::assertSame($expected, Decimal::format($scaled, $decimals, $minDecimals));
    }
}
