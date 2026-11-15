<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;

/**
 * Event port discarding every event (no event bus configured).
 */
final class NullEventPublisher implements EventPublisher
{
    public function publish(DomainEvent ...$events): void {}
}
