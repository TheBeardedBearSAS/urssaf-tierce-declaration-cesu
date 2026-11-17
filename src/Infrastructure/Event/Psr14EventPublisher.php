<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;

/**
 * Event port dispatching each domain event, in order, through a PSR-14 dispatcher.
 */
final readonly class Psr14EventPublisher implements EventPublisher
{
    public function __construct(private EventDispatcherInterface $dispatcher) {}

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
