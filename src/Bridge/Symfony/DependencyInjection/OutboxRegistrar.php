<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

use DateInterval;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\RelayOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\Psr14IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;

/**
 * The outbox (written by the UnitOfWork, which autowires it), its sink, relay and purge.
 *
 * Source: reference OutboxRegistrar, lot 6 design §4.3, ADR 0012.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class OutboxRegistrar
{
    public const SINK_MESSENGER = 'messenger';
    public const SINK_EVENT_DISPATCHER = 'event_dispatcher';

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $outbox = $config['outbox'];
        $this->registerOutbox($outbox['service']);
        $this->registerSink($outbox['sink'], $outbox['bus']);
        $this->services->set(RelayOutboxHandler::class)->autowire()->public();
        $this->services->set(PurgeOutboxHandler::class)->autowire()->public();
        $this->services->set(RelayOutboxCommand::class)->autowire()->tag('console.command');
        $this->services->set(PurgeOutboxCommand::class)
            ->args([new Reference(PurgeOutboxHandler::class), new Definition(DateInterval::class, [$outbox['retention']])])
            ->tag('console.command');
    }

    /**
     * Without "service", the in-memory outbox: the "custom" driver cannot reach it (it requires "outbox.service").
     */
    private function registerOutbox(?string $service): void
    {
        if ($service !== null) {
            $this->services->alias(Outbox::class, $service);

            return;
        }
        $this->services->set(Outbox::class, InMemoryOutbox::class);
    }

    private function registerSink(string $sink, string $bus): void
    {
        if ($sink === self::SINK_MESSENGER) {
            $this->services->set(IntegrationEventSink::class, MessengerIntegrationEventSink::class)->args([new Reference($bus)]);

            return;
        }
        if ($sink === self::SINK_EVENT_DISPATCHER) {
            $this->services->set(IntegrationEventSink::class, Psr14IntegrationEventSink::class)->args([new Reference('event_dispatcher')]);

            return;
        }
        $this->services->alias(IntegrationEventSink::class, $sink);
    }
}
