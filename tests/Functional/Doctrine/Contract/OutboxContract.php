<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateNotified;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Behaviour shared by every Outbox adapter (ADR 0012).
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 */
trait OutboxContract
{
    abstract protected function createOutbox(): Outbox;

    public function testTheAppendedEventsArePendingInRecordingOrder(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::notified('e1'), self::rejected('e2'));
        $outbox->append(self::notified('e3'));

        $pending = $outbox->pending(10);

        static::assertSame(['e1', 'e2', 'e3'], self::ids($pending));
        static::assertEquals(self::rejected('e2'), $pending[1]->event);
        static::assertSame([0, 0, 0], array_map(static fn(OutboxEntry $entry): int => $entry->attempts, $pending));
        static::assertLessThan($pending[1]->sequence, $pending[0]->sequence);
        static::assertLessThan($pending[2]->sequence, $pending[1]->sequence);
        static::assertSame(['e1', 'e2'], self::ids($outbox->pending(2)));
    }

    public function testADispatchedEntryIsNoLongerPending(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::notified('e1'), self::notified('e2'));

        $outbox->markDispatched($outbox->pending(1)[0], Instant::fromIso8601('2026-11-15T10:00:00Z'));

        static::assertSame(['e2'], self::ids($outbox->pending(10)));
    }

    public function testAFailedEntryStaysPendingWithOneMoreAttempt(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::notified('e1'));

        $outbox->markFailed($outbox->pending(1)[0], 'Broker down.');
        $outbox->markFailed($outbox->pending(1)[0], 'Broker still down.');

        $pending = $outbox->pending(10);
        static::assertSame(['e1'], self::ids($pending));
        static::assertSame(2, $pending[0]->attempts);
    }

    public function testThePurgeDeletesTheEntriesDispatchedBeforeTheInstantOnly(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::notified('e1'), self::notified('e2'), self::notified('e3'));
        [$first, $second] = $outbox->pending(2);
        $outbox->markDispatched($first, Instant::fromIso8601('2026-11-15T10:00:00Z'));
        $outbox->markDispatched($second, Instant::fromIso8601('2026-11-16T10:00:00Z'));

        static::assertSame(0, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-11-15T10:00:00Z')));
        static::assertSame(1, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-11-16T10:00:00Z')));
        static::assertSame(1, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-11-17T10:00:00Z')));
        static::assertSame(['e3'], self::ids($outbox->pending(10)));
    }

    private static function notified(string $eventId): MandateNotified
    {
        return new MandateNotified($eventId, '2026-11-15T10:00:00Z', 'tenant-1', 'mandate-1', 'employer-1', 'Z0003752390009');
    }

    private static function rejected(string $eventId): DeclarationRejected
    {
        return new DeclarationRejected($eventId, '2026-11-15T10:00:00Z', 'tenant-1', 'record-1', 'declaration-1', ['ERR_METIER_1']);
    }

    /**
     * @param list<OutboxEntry> $entries
     *
     * @return list<string>
     */
    private static function ids(array $entries): array
    {
        return array_map(static fn(OutboxEntry $entry): string => $entry->event->eventId, $entries);
    }
}
