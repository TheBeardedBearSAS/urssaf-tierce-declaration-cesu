<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;

final class PurgeOutboxHandlerTest extends TestCase
{
    public function testThePurgeKeepsTheEntriesDispatchedWithinTheRetention(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(new DeclarationAlreadyDeclared('e1', '2026-11-15T10:00:00Z', 'tenant-1', 'record-1', 'declaration-1'));
        $outbox->markDispatched($outbox->pending(1)[0], Instant::fromIso8601('2026-11-15T10:00:00Z'));
        $purge = new PurgeOutboxHandler($outbox, new FrozenClock(new DateTimeImmutable('2026-12-15T10:00:00Z')));

        static::assertSame(0, $purge(new PurgeOutbox(new DateInterval('P30D'))));
        static::assertSame(1, $purge(new PurgeOutbox(new DateInterval('P29DT23H59M59S'))));
        static::assertSame(0, $purge(new PurgeOutbox(new DateInterval('P1D'))));
    }
}
