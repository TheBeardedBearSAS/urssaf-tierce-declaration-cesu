<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\RateLimiter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\SlidingWindowRateLimiter;

final class SlidingWindowRateLimiterValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNonPositiveValues(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('provideNonPositiveValues')]
    public function testRejectsANonPositiveLimit(int $limit): void
    {
        $this->expectExceptionObject(InvalidValue::notPositive('rate_limit'));

        new SlidingWindowRateLimiter(new SystemClock(new MockClock()), $limit, 10);
    }

    #[DataProvider('provideNonPositiveValues')]
    public function testRejectsANonPositiveWindow(int $window): void
    {
        $this->expectExceptionObject(InvalidValue::notPositive('rate_limit_window'));

        new SlidingWindowRateLimiter(new SystemClock(new MockClock()), 10, $window);
    }
}
