<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Journal;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\JournalingOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\RecordingTransactionManager;

final class UnitOfWorkOutboxTest extends TestCase
{
    public function testTheIntegrationEventsAreAppendedInsideTheTransactionAfterTheOperation(): void
    {
        $journal = new Journal();
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), new NullEventPublisher(), new IgnoredFailures(), new JournalingOutbox($journal));

        $result = $unitOfWork->run(static function (RecordedEvents $events) use ($journal): string {
            $journal->add('work');
            $events->record(IntegrationEventSamples::declarationDeclared(), IntegrationEventSamples::declarationRequeued(), IntegrationEventSamples::mandateNotified());

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame(['begin', 'work', 'append:urssaf.cesu.declaration.declared,urssaf.cesu.mandate.notified', 'commit'], $journal->entries());
    }

    public function testNothingIsAppendedWhenTheOperationFails(): void
    {
        $journal = new Journal();
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), new NullEventPublisher(), new IgnoredFailures(), new JournalingOutbox($journal));

        $failure = new RuntimeException('failed');
        $this->expectExceptionObject($failure);

        try {
            $unitOfWork->run(static function (RecordedEvents $events) use ($failure): never {
                $events->record(IntegrationEventSamples::declarationDeclared());

                throw $failure;
            });
        } finally {
            static::assertSame(['begin', 'rollback'], $journal->entries());
        }
    }

    public function testTheDefaultOutboxIgnoresTheEvents(): void
    {
        $outbox = new IgnoredOutbox();
        $event = new DeclarationDeclared('e1', '2026-11-15T10:00:00Z', 'tenant-1', 'record-1', 'declaration-1', 'id-1');
        $now = Instant::fromDateTime(new DateTimeImmutable('2026-11-15T10:00:00Z'));

        $outbox->append($event);

        static::assertSame([], $outbox->pending(10));
        $entry = new OutboxEntry(1, $event, 0);
        $outbox->markDispatched($entry, $now);
        $outbox->markFailed($entry, 'ignored');
        static::assertSame([], $outbox->pending(10));
        static::assertSame(0, $outbox->purgeDispatchedBefore($now));
    }
}
