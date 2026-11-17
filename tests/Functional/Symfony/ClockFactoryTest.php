<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\ClockFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UtcSystemClock;

final class ClockFactoryTest extends TestCase
{
    public function testTheClockOfTheApplicationIsUsedWhenThereIsOne(): void
    {
        $clock = new MockClock('2026-11-15 10:00:00 UTC');

        static::assertSame('2026-11-15T10:00:00+00:00', ClockFactory::create($clock)->now()->format(DATE_ATOM));
    }

    public function testTheUtcSystemClockIsTheFallback(): void
    {
        static::assertInstanceOf(UtcSystemClock::class, ClockFactory::create(null));
    }
}
