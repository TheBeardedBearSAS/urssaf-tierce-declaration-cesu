<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Outbox;

use Override;
use Symfony\Component\Messenger\MessageBusInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;

/**
 * Dispatches the integration events on a Messenger bus: route them to a transport of your own and handle them with
 * message handlers (#[AsMessageHandler]). Adapted from the reference package, lot 6 design 7.5.
 */
final readonly class MessengerIntegrationEventSink implements IntegrationEventSink
{
    public function __construct(private MessageBusInterface $bus) {}

    #[Override]
    public function dispatch(IntegrationEvent $event): void
    {
        $this->bus->dispatch($event);
    }
}
