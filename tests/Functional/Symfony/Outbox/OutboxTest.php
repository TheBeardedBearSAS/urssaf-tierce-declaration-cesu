<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Outbox;

use DateInterval;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\Psr14IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\RecordingMessageBus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;

/**
 * One method per behaviour of the outbox integration (lot 6 design 7.5; reference OutboxTest).
 */
final class OutboxTest extends TestCase
{
    private const ENABLED = ['outbox' => ['enabled' => true, 'sink' => 'test.sink']];

    private const CUSTOM = ['persistence' => ['driver' => 'custom', 'employer_account_repository' => 'test.accounts', 'mandate_repository' => 'test.mandates', 'declaration_record_repository' => 'test.declarations']];

    public function testTheOutboxIsDisabledByDefault(): void
    {
        $container = (new TestKernel(['scheduler' => ['enabled' => true]]))->definitions();

        static::assertFalse($container->hasDefinition(Outbox::class) || $container->hasAlias(Outbox::class));
        static::assertFalse($container->hasDefinition(RelayOutboxHandler::class));
        static::assertSame(
            ['sync_employer_statuses' => 'PT1H', 'reconcile_stale_declarations' => 'PT15M', 'relay_outbox' => null, 'purge_outbox' => null],
            $container->getDefinition(UrssafScheduleProvider::class)->getArgument(0),
        );
    }

    public function testTheEventsOfAUseCaseAreRelayedToTheSink(): void
    {
        $harness = Harness::boot(self::ENABLED);
        Scenario::importedEmployer($harness);
        static::assertCount(1, $harness->privateService(Outbox::class, InMemoryOutbox::class)->pending(10));

        $report = $harness->get(RelayOutboxHandler::class)(new RelayOutbox(null));

        static::assertSame([1, 0, 0], [$report->dispatched, $report->failed, $report->deferred]);
        static::assertCount(1, $harness->privateService('test.sink', RecordingIntegrationEventSink::class)->deliveredIds());
        static::assertSame([], $harness->privateService(Outbox::class, InMemoryOutbox::class)->pending(10));
    }

    public function testAFailedDeliveryStaysInTheOutbox(): void
    {
        $harness = Harness::boot(self::ENABLED);
        $outbox = $harness->privateService(Outbox::class, InMemoryOutbox::class);
        $outbox->append(new EmployerRegistered('e1', '2026-11-15T10:00:00Z', 'tenant-1', 'refused-aggregate', 'employer-1', 'Z0003752390009', 'imported'));

        $report = $harness->get(RelayOutboxHandler::class)(new RelayOutbox(5));

        static::assertSame([0, 1, 0], [$report->dispatched, $report->failed, $report->deferred]);
        static::assertCount(1, $outbox->pending(10));
    }

    public function testThePurgeUsesTheRetention(): void
    {
        $config = ['outbox' => [...self::ENABLED['outbox'], 'retention' => 'P1D']];
        $harness = Harness::boot($config);
        $definition = (new TestKernel($config))->definitions()->getDefinition(PurgeOutboxCommand::class);

        static::assertSame(0, $harness->get(PurgeOutboxHandler::class)(new PurgeOutbox(new DateInterval('P1D'))));
        static::assertEquals(new Definition(DateInterval::class, ['P1D']), $definition->getArgument(1));
    }

    public function testAServiceOfTheHostCanBeTheOutbox(): void
    {
        $container = (new TestKernel([...self::CUSTOM, 'outbox' => ['enabled' => true, 'service' => 'host.outbox']]))->definitions();

        static::assertSame('host.outbox', (string) $container->getAlias(Outbox::class));
    }

    public function testTheCustomDriverNeedsAnOutboxService(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "custom" persistence driver requires "outbox.service" when the outbox is enabled.');

        Harness::boot([...self::CUSTOM, 'outbox' => ['enabled' => true]]);
    }

    public function testTheInMemoryDriverHasAnInMemoryOutbox(): void
    {
        $definition = (new TestKernel(['outbox' => ['enabled' => true]]))->definitions()->getDefinition(Outbox::class);

        static::assertSame(InMemoryOutbox::class, $definition->getClass());
        static::assertSame([], $definition->getArguments());
    }

    public function testTheSinkIsTheBusTheEventDispatcherOrAService(): void
    {
        $messenger = (new TestKernel(['outbox' => ['enabled' => true, 'bus' => 'event.bus']]))->definitions()->getDefinition(IntegrationEventSink::class);
        $default = (new TestKernel(['outbox' => ['enabled' => true]]))->definitions()->getDefinition(IntegrationEventSink::class);
        $dispatcher = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]))->definitions()->getDefinition(IntegrationEventSink::class);
        $service = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'host.sink']]))->definitions()->getAlias(IntegrationEventSink::class);

        static::assertSame(MessengerIntegrationEventSink::class, $messenger->getClass());
        static::assertEquals([new Reference('event.bus')], $messenger->getArguments());
        static::assertEquals([new Reference('messenger.default_bus')], $default->getArguments());
        static::assertSame(Psr14IntegrationEventSink::class, $dispatcher->getClass());
        static::assertEquals([new Reference('event_dispatcher')], $dispatcher->getArguments());
        static::assertSame('host.sink', (string) $service);
    }

    public function testTheOutboxTasksAreScheduledWithTheOutbox(): void
    {
        // @mago-expect analysis:mixed-assignment Definition arguments are untyped: the assertion below narrows it.
        $frequencies = (new TestKernel(['outbox' => ['enabled' => true], 'scheduler' => ['enabled' => true]]))->definitions()->getDefinition(UrssafScheduleProvider::class)->getArgument(0);

        static::assertIsArray($frequencies);
        static::assertSame('PT1M', $frequencies['relay_outbox']);
        static::assertSame('P1D', $frequencies['purge_outbox']);
    }

    public function testTheMessengerSinkDeliversTheEventOnTheBusAndTheDispatcherSinkToItsListeners(): void
    {
        $event = new EmployerRegistered('e1', '2026-11-15T10:00:00Z', 'tenant-1', 'account-1', 'employer-1', 'Z0003752390009', 'imported');
        $bus = new RecordingMessageBus();
        $dispatcher = new EventDispatcher();
        $received = [];
        $dispatcher->addListener(EmployerRegistered::class, static function (IntegrationEvent $dispatched) use (&$received): void {
            $received[] = $dispatched;
        });

        (new MessengerIntegrationEventSink($bus))->dispatch($event);
        (new Psr14IntegrationEventSink($dispatcher))->dispatch($event);

        static::assertSame([$event], $bus->messages());
        static::assertSame([$event], $received);
    }
}
