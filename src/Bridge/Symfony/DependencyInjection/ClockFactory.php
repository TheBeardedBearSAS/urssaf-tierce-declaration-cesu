<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

use Psr\Clock\ClockInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UtcSystemClock;

/**
 * Container factory of the clock of the package: the application "clock" service (PSR-20) when it exists, the UTC
 * system clock otherwise (symfony/clock is not required).
 *
 * Source: reference ClockFactory, lot 6 design §4.3.
 */
final class ClockFactory
{
    public static function create(?ClockInterface $applicationClock): Clock
    {
        return $applicationClock instanceof ClockInterface ? new SystemClock($applicationClock) : new UtcSystemClock();
    }
}
