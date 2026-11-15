<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;

/**
 * Clock port backed by any PSR-20 clock (the framework's system clock in production).
 */
final readonly class SystemClock implements Clock
{
    public function __construct(private ClockInterface $clock) {}

    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
