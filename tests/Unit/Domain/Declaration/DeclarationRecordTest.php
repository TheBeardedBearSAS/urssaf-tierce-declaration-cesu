<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationCredentialsRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRecorded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRequeued;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationSubmissionFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * One method per command and scenario of the aggregate.
 *
 * @mago-expect lint:too-many-methods
 */
final class DeclarationRecordTest extends TestCase
{
    /**
     * @return list<SubmissionError>
     */
    private static function errors(): array
    {
        return [SubmissionError::of('ERR_METIER', 'Une erreur métier est survenue', 'Détail')];
    }

    private static function submitting(): DeclarationRecord
    {
        $record = DeclarationSamples::record();
        $record->startSubmission(DeclarationSamples::later(1));
        $record->releaseEvents();

        return $record;
    }

    private static function failed(int $attempts): DeclarationRecord
    {
        $record = DeclarationSamples::record();
        for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
            $record->startSubmission(DeclarationSamples::later($attempt * 10));
            $record->markFailed(self::errors(), DeclarationSamples::later($attempt * 10 + 1));
            if ($attempt < $attempts) {
                $record->retry(DeclarationSamples::later($attempt * 10 + 2), 100);
            }
        }
        $record->releaseEvents();

        return $record;
    }

    private static function singleEvent(DeclarationRecord $record): DomainEvent
    {
        $events = $record->releaseEvents();
        static::assertCount(1, $events);
        static::assertSame([], $record->releaseEvents());

        return $events[0];
    }

    public function testRecordingStartsPendingAndRecordsAnEvent(): void
    {
        $snapshot = DeclarationSamples::snapshot();
        $record = DeclarationRecord::record(
            DeclarationSamples::recordId(),
            DeclarationSamples::tenantId(),
            DeclarationSamples::externalReference(),
            $snapshot,
            DeclarationSamples::now(),
        );

        static::assertSame('record-1', $record->id()->value());
        static::assertSame('tenant-1', $record->tenantId()->value());
        static::assertSame('host-declaration-1', $record->externalReference()->value());
        static::assertSame($snapshot, $record->snapshot());
        static::assertTrue($record->createdAt()->equals(DeclarationSamples::now()));
        static::assertTrue($record->updatedAt()->equals(DeclarationSamples::now()));
        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertSame(DeclarationState::Pending, $record->status()->state());
        static::assertSame(0, $record->attempts());
        static::assertNull($record->lastAttemptAt());
        static::assertSame([], $record->errors());
        static::assertNull($record->declarationId());
        static::assertNull($record->result());
        static::assertNull($record->outcomeUnknownReason());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationRecorded::class, $event);
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('host-declaration-1', $event->externalReference()->value());
        static::assertSame(DeclarationSamples::EMPLOYER_CESU_NUMBER, $event->employerCesuNumber()->value());
        static::assertEquals(DeclarationSamples::now()->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testStartSubmissionCountsTheAttemptWithoutEvent(): void
    {
        $record = DeclarationSamples::record();
        $now = DeclarationSamples::later(5);

        $record->startSubmission($now);

        static::assertSame(DeclarationState::Submitting, $record->state());
        static::assertSame(1, $record->attempts());
        static::assertSame($now, $record->lastAttemptAt());
        static::assertSame($now, $record->updatedAt());
        static::assertSame([], $record->releaseEvents());
    }

    public function testMarkDeclaredStoresTheResult(): void
    {
        $record = self::submitting();
        $result = DeclarationSamples::result();
        $now = DeclarationSamples::later(10);

        $record->markDeclared($result, $now);

        static::assertSame(DeclarationState::Declared, $record->state());
        static::assertSame($result, $record->result());
        static::assertSame('209299542888', $record->declarationId()?->value());
        static::assertSame($now, $record->updatedAt());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationDeclared::class, $event);
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('host-declaration-1', $event->externalReference()->value());
        static::assertSame('209299542888', $event->declarationId()->value());
        static::assertEquals($now->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkRejectedStoresTheErrors(): void
    {
        $record = self::submitting();
        $errors = self::errors();
        $now = DeclarationSamples::later(10);

        $record->markRejected($errors, $now);

        static::assertSame(DeclarationState::Rejected, $record->state());
        static::assertSame($errors, $record->errors());
        static::assertSame($now, $record->updatedAt());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationRejected::class, $event);
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('host-declaration-1', $event->externalReference()->value());
        static::assertSame($errors, $event->errors());
        static::assertEquals($now->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkAlreadyDeclaredStoresTheErrors(): void
    {
        $record = self::submitting();
        $errors = self::errors();
        $now = DeclarationSamples::later(10);

        $record->markAlreadyDeclared($errors, $now);

        static::assertSame(DeclarationState::AlreadyDeclared, $record->state());
        static::assertSame($errors, $record->errors());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationAlreadyDeclared::class, $event);
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('host-declaration-1', $event->externalReference()->value());
        static::assertEquals($now->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkOutcomeUnknownStoresTheReasonAndTheErrors(): void
    {
        $record = self::submitting();
        $errors = self::errors();
        $now = DeclarationSamples::later(10);

        $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, $errors, $now);

        static::assertSame(DeclarationState::OutcomeUnknown, $record->state());
        static::assertSame(OutcomeUnknownReason::MayHaveReachedUrssaf, $record->outcomeUnknownReason());
        static::assertSame($errors, $record->errors());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationOutcomeUnknown::class, $event);
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('host-declaration-1', $event->externalReference()->value());
        static::assertSame(OutcomeUnknownReason::MayHaveReachedUrssaf, $event->reason());
        static::assertEquals($now->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkFailedStoresTheErrors(): void
    {
        $record = self::submitting();
        $errors = self::errors();
        $now = DeclarationSamples::later(10);

        $record->markFailed($errors, $now);

        static::assertSame(DeclarationState::Error, $record->state());
        static::assertSame($errors, $record->errors());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationSubmissionFailed::class, $event);
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame($errors, $event->errors());
        static::assertEquals($now->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testMarkInvalidCredentials(): void
    {
        $record = self::submitting();
        $now = DeclarationSamples::later(10);

        $record->markInvalidCredentials($now);

        static::assertSame(DeclarationState::InvalidCredentials, $record->state());
        static::assertSame([], $record->errors());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationCredentialsRejected::class, $event);
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertEquals($now->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testRetryRequeuesAFailedSubmissionKeepingTheErrors(): void
    {
        $record = self::failed(1);
        $now = DeclarationSamples::later(100);

        $record->retry($now);

        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertSame(self::errors()[0]->code(), $record->errors()[0]->code());
        static::assertSame(1, $record->attempts());
        static::assertSame($now, $record->updatedAt());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationRequeued::class, $event);
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame(DeclarationState::Error, $event->from());
        static::assertEquals($now->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testRetryAfterInvalidCredentialsIsAlwaysAllowed(): void
    {
        $record = self::submitting();
        $record->markInvalidCredentials(DeclarationSamples::later(10));
        $record->releaseEvents();

        $record->retry(DeclarationSamples::later(20), 1);

        static::assertSame(DeclarationState::Pending, $record->state());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationRequeued::class, $event);
        static::assertSame(DeclarationState::InvalidCredentials, $event->from());
    }

    /**
     * @return iterable<string, array{int, positive-int|null}>
     */
    public static function provideAllowedRetries(): iterable
    {
        yield 'explicit limit not reached' => [2, 3];
        yield 'default limit not reached' => [4, null];
    }

    /**
     * @param positive-int|null $max
     */
    #[DataProvider('provideAllowedRetries')]
    public function testRetryIsAllowedWhileAttemptsRemain(int $attempts, ?int $max): void
    {
        $record = self::failed($attempts);

        static::assertTrue($max === null ? $record->canRetry() : $record->canRetry($max));
        $max === null ? $record->retry(DeclarationSamples::later(1000)) : $record->retry(DeclarationSamples::later(1000), $max);

        static::assertSame(DeclarationState::Pending, $record->state());
    }

    /**
     * @return iterable<string, array{int, positive-int|null}>
     */
    public static function provideRefusedRetries(): iterable
    {
        yield 'explicit limit reached' => [3, 3];
        yield 'explicit limit exceeded' => [4, 3];
        yield 'default limit reached' => [5, null];
    }

    /**
     * @param positive-int|null $max
     */
    #[DataProvider('provideRefusedRetries')]
    public function testRetryIsRefusedOnceTheMaximumAttemptsAreReached(int $attempts, ?int $max): void
    {
        $record = self::failed($attempts);

        static::assertFalse($max === null ? $record->canRetry() : $record->canRetry($max));
        try {
            $max === null ? $record->retry(DeclarationSamples::later(1000)) : $record->retry(DeclarationSamples::later(1000), $max);
            static::fail('Expected the maximum number of attempts to be reached.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.max_attempts_reached', $exception->violationCode());
            static::assertSame(['attempts' => $attempts, 'max_attempts' => $max ?? 5], $exception->params());
            static::assertSame('The maximum number of submission attempts is reached.', $exception->getMessage());
        }
        static::assertSame(DeclarationState::Error, $record->state());
        static::assertSame([], $record->releaseEvents());
    }

    public function testCanRetryIsNotLimitedOutsideTheErrorState(): void
    {
        static::assertTrue(DeclarationSamples::record()->canRetry(1));
        $record = self::submitting();
        $record->markInvalidCredentials(DeclarationSamples::later(10));
        static::assertTrue($record->canRetry(1));
    }

    public function testAnUnknownOutcomeIsNeverRetriedAutomatically(): void
    {
        $record = self::submitting();
        $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, [], DeclarationSamples::later(10));
        $record->releaseEvents();

        try {
            $record->retry(DeclarationSamples::later(20));
            static::fail('Expected a refused transition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('outcome_unknown', $exception->from());
            static::assertSame('pending', $exception->to());
        }
        static::assertSame(DeclarationState::OutcomeUnknown, $record->state());
        static::assertSame([], $record->releaseEvents());
    }

    public function testResolveAsDeclaredStoresTheIdWithoutResult(): void
    {
        $record = self::submitting();
        $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, self::errors(), DeclarationSamples::later(10));
        $record->releaseEvents();
        $id = DeclarationId::fromString('found-by-hand');
        $now = DeclarationSamples::later(20);

        $record->resolveAsDeclared($id, $now);

        static::assertSame(DeclarationState::Declared, $record->state());
        static::assertSame($id, $record->declarationId());
        static::assertNull($record->result());
        static::assertSame([], $record->errors());
        static::assertNull($record->outcomeUnknownReason());
        static::assertSame($now, $record->updatedAt());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationDeclared::class, $event);
        static::assertSame($id, $event->declarationId());
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('host-declaration-1', $event->externalReference()->value());
        static::assertEquals($now->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testResolveAsNotReceivedRequeuesAnUnknownOutcome(): void
    {
        $record = self::submitting();
        $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, self::errors(), DeclarationSamples::later(10));
        $record->releaseEvents();
        $now = DeclarationSamples::later(20);

        $record->resolveAsNotReceived($now);

        static::assertSame(DeclarationState::Pending, $record->state());
        static::assertNull($record->outcomeUnknownReason());
        static::assertSame(1, $record->attempts());
        static::assertSame($now, $record->updatedAt());
        $event = self::singleEvent($record);
        static::assertInstanceOf(DeclarationRequeued::class, $event);
        static::assertSame(DeclarationState::OutcomeUnknown, $event->from());
        static::assertSame('record-1', $event->recordId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertEquals($now->toDateTimeImmutable(), $event->occurredAt());
    }

    public function testResolveAsNotReceivedIsRefusedOutsideAnUnknownOutcome(): void
    {
        $record = self::failed(1);

        try {
            $record->resolveAsNotReceived(DeclarationSamples::later(100));
            static::fail('Expected a refused transition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('error', $exception->from());
            static::assertSame('pending', $exception->to());
        }
        static::assertSame(DeclarationState::Error, $record->state());
        static::assertSame([], $record->releaseEvents());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideStaleChecks(): iterable
    {
        yield 'one second before' => ['PT9M59S', false];
        yield 'exactly the delay' => ['PT10M', true];
        yield 'one second after' => ['PT10M1S', true];
    }

    #[DataProvider('provideStaleChecks')]
    public function testIsStaleSubmission(string $elapsed, bool $expected): void
    {
        $record = DeclarationSamples::record();
        $started = Instant::fromIso8601('2026-11-15T10:00:00Z');
        $record->startSubmission($started);
        $now = Instant::fromDateTime($started->toDateTimeImmutable()->add(new DateInterval($elapsed)));

        static::assertSame($expected, $record->isStaleSubmission($now, new DateInterval('PT10M')));
    }

    public function testOnlyASubmittingRecordCanBeStale(): void
    {
        $far = DeclarationSamples::later(86_400);

        static::assertFalse(DeclarationSamples::record()->isStaleSubmission($far, new DateInterval('PT1S')));
        $declared = self::submitting();
        $declared->markDeclared(DeclarationSamples::result(), DeclarationSamples::later(10));
        static::assertFalse($declared->isStaleSubmission($far, new DateInterval('PT1S')));
    }

    public function testHasSameContentAs(): void
    {
        $record = DeclarationSamples::record();

        static::assertTrue($record->hasSameContentAs(DeclarationSamples::snapshot()));
        static::assertFalse($record->hasSameContentAs(DeclarationSamples::snapshotOf(DeclarationSamples::cesuPlus())));
    }

    public function testReleaseEventsReturnsThemInOrderAndForgetsThem(): void
    {
        $record = DeclarationSamples::record();
        $record->startSubmission(DeclarationSamples::later(1));
        $record->markFailed([], DeclarationSamples::later(2));
        $record->retry(DeclarationSamples::later(3));

        $events = $record->releaseEvents();

        static::assertCount(2, $events);
        static::assertInstanceOf(DeclarationSubmissionFailed::class, $events[0]);
        static::assertInstanceOf(DeclarationRequeued::class, $events[1]);
        static::assertSame([], $record->releaseEvents());
    }

    public function testRecordingKeepsItsCreationEventUntilReleased(): void
    {
        $record = DeclarationRecord::record(
            DeclarationSamples::recordId(),
            DeclarationSamples::tenantId(),
            DeclarationSamples::externalReference(),
            DeclarationSamples::snapshot(),
            DeclarationSamples::now(),
        );
        $record->startSubmission(DeclarationSamples::later(1));

        $events = $record->releaseEvents();

        static::assertCount(1, $events);
        static::assertInstanceOf(DeclarationRecorded::class, $events[0]);
    }
}
