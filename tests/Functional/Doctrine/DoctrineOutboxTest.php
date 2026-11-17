<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\OutboxContract;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\DomainEventSamples;

final class DoctrineOutboxTest extends TestCase
{
    use OutboxContract;

    protected function createOutbox(): Outbox
    {
        return new DoctrineOutbox(TestDatabase::entityManager());
    }

    public function testTheEventsAreWrittenWithTheStateChangeOrNotAtAll(): void
    {
        $entityManager = TestDatabase::entityManager();
        $outbox = new DoctrineOutbox($entityManager);
        $unitOfWork = self::unitOfWork($entityManager, $outbox);

        $unitOfWork->run(static fn(RecordedEvents $events) => $events->record(DomainEventSamples::mandateNotified()));
        try {
            $unitOfWork->run(static function (RecordedEvents $events): never {
                $events->record(DomainEventSamples::mandateConfirmed());

                throw new RuntimeException('Rolled back.');
            });
        } catch (RuntimeException $failure) {
            static::assertNotSame('', $failure->getMessage());
        }

        $pending = $outbox->pending(10);
        static::assertCount(1, $pending);
        static::assertSame('urssaf.cesu.mandate.notified', $pending[0]->event->type());
        $row = $entityManager->getConnection()->fetchAssociative('SELECT * FROM urssaf_cesu_outbox');
        static::assertIsArray($row);
        static::assertEquals(
            [$pending[0]->event->eventId, $pending[0]->event->type(), 1, $pending[0]->event->tenantId, $pending[0]->event->aggregateId, $pending[0]->event->occurredAt, null, 0, null],
            [$row['event_id'], $row['type'], $row['version'], $row['tenant_id'], $row['aggregate_id'], $row['occurred_at'], $row['dispatched_at'], $row['attempts'], $row['last_error']],
        );
        static::assertIsString($row['payload']);
        static::assertSame($pending[0]->event->toArray(), json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testTheLastFailureIsKept(): void
    {
        $entityManager = TestDatabase::entityManager();
        $outbox = new DoctrineOutbox($entityManager);
        self::unitOfWork($entityManager, $outbox)->run(static fn(RecordedEvents $events) => $events->record(DomainEventSamples::mandateNotified()));

        $outbox->markFailed($outbox->pending(1)[0], 'Broker down.');

        static::assertSame('Broker down.', $entityManager->getConnection()->fetchOne('SELECT last_error FROM urssaf_cesu_outbox'));
    }

    private static function unitOfWork(EntityManagerInterface $entityManager, Outbox $outbox): UnitOfWork
    {
        return new UnitOfWork(new DoctrineTransactionManager($entityManager), new NullEventPublisher(), new IgnoredFailures(), $outbox);
    }
}
