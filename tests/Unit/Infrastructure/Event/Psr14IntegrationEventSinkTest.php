<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Event;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\Psr14IntegrationEventSink;

final class Psr14IntegrationEventSinkTest extends TestCase
{
    public function testDispatchesEachIntegrationEventThroughThePsr14Dispatcher(): void
    {
        $dispatcher = new RecordingDispatcher();
        $first = new DeclarationAlreadyDeclared('e1', '2026-11-15T10:00:00Z', 'tenant-1', 'record-1', 'declaration-1');
        $second = new DeclarationAlreadyDeclared('e2', '2026-11-15T10:00:00Z', 'tenant-1', 'record-2', 'declaration-2');
        $sink = new Psr14IntegrationEventSink($dispatcher);

        $sink->dispatch($first);
        $sink->dispatch($second);

        static::assertSame([$first, $second], $dispatcher->dispatched());
    }
}
