<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\FailingEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Journal;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\RecordingEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\RecordingFailureReporter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\RecordingTransactionManager;

final class UnitOfWorkTest extends TestCase
{
    public function testPublishesTheRecordedEventsAfterTheCommit(): void
    {
        $journal = new Journal();
        $publisher = new RecordingEventPublisher($journal);
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), $publisher);
        $first = IntegrationEventSamples::declarationRecorded();
        $second = IntegrationEventSamples::declarationRequeued();

        $result = $unitOfWork->run(static function (RecordedEvents $events) use ($first, $second, $journal): string {
            $journal->add('work');
            $events->record($first);
            $events->record($second);

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame([$first, $second], $publisher->events());
        static::assertSame(['begin', 'work', 'commit', 'publish:DeclarationRecorded', 'publish:DeclarationRequeued'], $journal->entries());
    }

    public function testPublishesNothingWhenTheOperationFails(): void
    {
        $journal = new Journal();
        $publisher = new RecordingEventPublisher($journal);
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), $publisher);
        $failure = new RuntimeException('failure');
        $this->expectExceptionObject($failure);

        try {
            $unitOfWork->run(static function (RecordedEvents $events) use ($failure): never {
                $events->record(IntegrationEventSamples::declarationRecorded());

                throw $failure;
            });
        } finally {
            static::assertSame([], $publisher->events());
            static::assertSame(['begin', 'rollback'], $journal->entries());
        }
    }

    public function testRecordedEventsKeepTheOrder(): void
    {
        $first = IntegrationEventSamples::declarationRecorded();
        $second = IntegrationEventSamples::declarationRequeued();
        $events = new RecordedEvents();

        static::assertSame([], $events->all());
        $events->record();
        $events->record(...['first' => $first, 'second' => $second]);
        $events->record($first);

        static::assertSame([$first, $second, $first], $events->all());
    }

    public function testReportsAPublicationFailureWithoutFailingTheCommittedOperation(): void
    {
        $journal = new Journal();
        $transactions = new RecordingTransactionManager($journal);
        $publisher = new FailingEventPublisher(new RuntimeException('broker down'));
        $reporter = new RecordingFailureReporter();
        $unitOfWork = new UnitOfWork($transactions, $publisher, $reporter);
        $event = IntegrationEventSamples::declarationRecorded();

        $result = $unitOfWork->run(static function (RecordedEvents $events) use ($event): string {
            $events->record($event);

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame([[$publisher->failure, [$event]]], $reporter->reports());
        static::assertSame(['begin', 'commit'], $journal->entries());
    }

    public function testIgnoresAPublicationFailureByDefault(): void
    {
        $transactions = new RecordingTransactionManager(new Journal());
        $unitOfWork = new UnitOfWork($transactions, new FailingEventPublisher(new RuntimeException('broker down')));

        static::assertSame('done', $unitOfWork->run(static fn(): string => 'done'));
        static::assertSame(1, $transactions->committed());
    }

    public function testTheDefaultFailureReporterDropsTheFailure(): void
    {
        (new IgnoredFailures())->eventPublicationFailed(new RuntimeException('ignored'), [IntegrationEventSamples::declarationRecorded()]);

        $unitOfWork = new UnitOfWork(new RecordingTransactionManager(new Journal()), new NullEventPublisher());

        static::assertSame(7, $unitOfWork->run(static fn(): int => 7));
    }
}
