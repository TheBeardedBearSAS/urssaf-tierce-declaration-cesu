<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;

final class RelayOutboxHandlerTest extends TestCase
{
    private const NOW = '2026-11-15T10:00:00Z';

    public function testThePendingEventsAreDeliveredInOrderThenMarkedAsDispatched(): void
    {
        $outbox = self::outbox(['e1', 'a'], ['e2', 'b'], ['e3', 'a']);
        $sink = new RecordingIntegrationEventSink();

        $report = self::handler($outbox, $sink)(new RelayOutbox(null));

        static::assertSame(['e1', 'e2', 'e3'], $sink->deliveredIds());
        static::assertSame([3, 0, 0], [$report->dispatched, $report->failed, $report->deferred]);
        static::assertSame([], $outbox->pending(10));
        static::assertSame(0, $outbox->purgeDispatchedBefore(Instant::fromIso8601(self::NOW)));
        static::assertSame(3, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-11-15T10:00:01Z')));
    }

    public function testAFailedEventStaysPendingAndHoldsBackTheNextEventsOfItsRecordOnly(): void
    {
        $outbox = self::outbox(['e1', 'a'], ['e2', 'b'], ['e3', 'a'], ['e4', 'c']);
        $sink = new RecordingIntegrationEventSink(['a']);

        $report = self::handler($outbox, $sink)(new RelayOutbox(null));

        static::assertSame(['e2', 'e4'], $sink->deliveredIds());
        static::assertSame([2, 1, 1], [$report->dispatched, $report->failed, $report->deferred]);
        $pending = $outbox->pending(10);
        static::assertSame([[1, 1], [3, 0]], array_map(static fn(OutboxEntry $entry): array => [$entry->sequence, $entry->attempts], $pending));
        static::assertSame('Broker down for a.', $outbox->lastError(1));
        static::assertNull($outbox->lastError(3));
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $outbox = self::outbox(['e1', 'a'], ['e2', 'b'], ['e3', 'c']);
        $sink = new RecordingIntegrationEventSink();
        $handler = new RelayOutboxHandler($outbox, $sink, self::clock(), new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('PT15M'), 5, true, false, 2, 2));

        $handler(new RelayOutbox(null));
        static::assertSame(['e1', 'e2'], $sink->deliveredIds());
        $handler(new RelayOutbox(1));
        static::assertSame(['e1', 'e2', 'e3'], $sink->deliveredIds());
    }

    public function testTheDispatchInstantIsTheCurrentInstant(): void
    {
        $outbox = self::outbox(['e1', 'a']);
        self::handler($outbox, new RecordingIntegrationEventSink())(new RelayOutbox(null));

        static::assertSame(0, $outbox->purgeDispatchedBefore(Instant::fromIso8601(self::NOW)));
        static::assertSame(1, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-11-15T10:00:01Z')));
    }

    /**
     * @param array{string, string} ...$events event id and aggregate id of each event
     */
    private static function outbox(array ...$events): InMemoryOutbox
    {
        $outbox = new InMemoryOutbox();
        foreach ($events as [$eventId, $aggregateId]) {
            $outbox->append(new DeclarationDeclared($eventId, self::NOW, 'tenant-1', $aggregateId, 'ref-' . $aggregateId, 'declaration-' . $aggregateId));
        }

        return $outbox;
    }

    private static function handler(InMemoryOutbox $outbox, RecordingIntegrationEventSink $sink): RelayOutboxHandler
    {
        return new RelayOutboxHandler($outbox, $sink, self::clock(), new WorkflowPolicy());
    }

    private static function clock(): FrozenClock
    {
        return new FrozenClock(new DateTimeImmutable(self::NOW));
    }
}
