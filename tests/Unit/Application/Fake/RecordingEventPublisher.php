<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use ReflectionClass;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;

final class RecordingEventPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function __construct(private readonly Journal $journal) {}

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
            $this->journal->add('publish:' . (new ReflectionClass($event))->getShortName());
        }
    }

    /**
     * @return list<DomainEvent>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return list<string> short class names of the published events
     */
    public function names(): array
    {
        return array_map(static fn(DomainEvent $event): string => (new ReflectionClass($event))->getShortName(), $this->events);
    }
}
