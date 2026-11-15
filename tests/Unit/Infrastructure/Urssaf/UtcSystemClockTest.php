<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UtcSystemClock;

final class UtcSystemClockTest extends TestCase
{
    public function testNowIsTheCurrentTimeInUtc(): void
    {
        $before = new DateTimeImmutable('now');

        $now = (new UtcSystemClock())->now();

        $after = new DateTimeImmutable('now');
        static::assertSame('UTC', $now->getTimezone()->getName());
        static::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        static::assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }
}
