<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

/**
 * One method per command and scenario of the status.
 *
 * @mago-expect lint:too-many-methods
 */
final class DeclarationStatusTransitionsTest extends TestCase
{
    public function testSubmissionStartedCountsTheAttemptAndKeepsTheRest(): void
    {
        $errors = [DeclarationSamples::submissionError('E1')];
        $result = DeclarationSamples::result();
        $pending = new DeclarationStatus(DeclarationState::Pending, 2, DeclarationSamples::later(-60), $errors, $result->declarationId(), $result, OutcomeUnknownReason::StaleSubmission, DeclarationSamples::later(-30));
        $now = DeclarationSamples::now();

        $submitting = $pending->submissionStarted($now);

        static::assertSame(DeclarationState::Submitting, $submitting->state());
        static::assertSame(3, $submitting->attempts());
        static::assertSame($now, $submitting->lastAttemptAt());
        static::assertSame($now, $submitting->updatedAt());
        static::assertSame($errors, $submitting->errors());
        static::assertSame($result->declarationId(), $submitting->declarationId());
        static::assertSame($result, $submitting->result());
        static::assertNull($submitting->outcomeUnknownReason());
        static::assertSame(DeclarationState::Pending, $pending->state());
    }

    public function testDeclaredStoresTheResultAndClearsTheErrors(): void
    {
        $result = DeclarationSamples::result();
        $now = DeclarationSamples::now();
        $declared = DeclarationSamples::statusIn(DeclarationState::Submitting, [DeclarationSamples::submissionError('E1')], null, null)->declared($result, $now);

        static::assertSame(DeclarationState::Declared, $declared->state());
        static::assertSame([], $declared->errors());
        static::assertSame($result->declarationId(), $declared->declarationId());
        static::assertSame($result, $declared->result());
        static::assertNull($declared->outcomeUnknownReason());
        static::assertSame($now, $declared->updatedAt());
        static::assertSame(2, $declared->attempts());
        static::assertTrue(DeclarationSamples::later(-60)->equals($declared->lastAttemptAt() ?? $now));
    }

    public function testResolvedAsDeclaredStoresTheIdWithoutResult(): void
    {
        $id = DeclarationId::fromString('found-by-hand');
        $now = DeclarationSamples::now();
        $status = new DeclarationStatus(
            DeclarationState::OutcomeUnknown,
            2,
            DeclarationSamples::later(-60),
            [DeclarationSamples::submissionError('E1')],
            null,
            DeclarationSamples::result(),
            OutcomeUnknownReason::StaleSubmission,
            DeclarationSamples::later(-30),
        );

        $declared = $status->resolvedAsDeclared($id, $now);

        static::assertSame(DeclarationState::Declared, $declared->state());
        static::assertSame([], $declared->errors());
        static::assertSame($id, $declared->declarationId());
        static::assertNull($declared->result());
        static::assertNull($declared->outcomeUnknownReason());
        static::assertSame($now, $declared->updatedAt());
        static::assertSame(2, $declared->attempts());
    }

    /**
     * @return iterable<string, array{DeclarationState}>
     */
    public static function provideErrorStates(): iterable
    {
        yield 'rejected' => [DeclarationState::Rejected];
        yield 'already declared' => [DeclarationState::AlreadyDeclared];
        yield 'error' => [DeclarationState::Error];
    }

    public function testRejectedStoresTheErrorsAndKeepsTheRest(): void
    {
        $now = DeclarationSamples::now();
        $given = [DeclarationSamples::submissionError('NEW')];
        $status = self::submittingWithHistory()->rejected($given, $now);

        self::assertRefusal($status, DeclarationState::Rejected, $given, $now);
    }

    public function testAlreadyDeclaredStoresTheErrorsAndKeepsTheRest(): void
    {
        $now = DeclarationSamples::now();
        $given = [DeclarationSamples::submissionError('NEW')];
        $status = self::submittingWithHistory()->alreadyDeclared($given, $now);

        self::assertRefusal($status, DeclarationState::AlreadyDeclared, $given, $now);
    }

    public function testFailedStoresTheErrorsAndKeepsTheRest(): void
    {
        $now = DeclarationSamples::now();
        $given = [DeclarationSamples::submissionError('NEW')];
        $status = self::submittingWithHistory()->failed($given, $now);

        self::assertRefusal($status, DeclarationState::Error, $given, $now);
    }

    private static function submittingWithHistory(): DeclarationStatus
    {
        $result = DeclarationSamples::result();

        return new DeclarationStatus(
            DeclarationState::Submitting,
            2,
            DeclarationSamples::later(-60),
            [DeclarationSamples::submissionError('OLD')],
            $result->declarationId(),
            $result,
            null,
            DeclarationSamples::later(-30),
        );
    }

    /**
     * @param list<SubmissionError> $given
     */
    private static function assertRefusal(DeclarationStatus $status, DeclarationState $target, array $given, Instant $now): void
    {
        $result = DeclarationSamples::result();

        static::assertSame($target, $status->state());
        static::assertSame($given, $status->errors());
        static::assertSame($result->declarationId()->value(), $status->declarationId()?->value());
        static::assertSame($result->declarationId()->value(), $status->result()?->declarationId()->value());
        static::assertNull($status->outcomeUnknownReason());
        static::assertSame($now, $status->updatedAt());
        static::assertSame(2, $status->attempts());
        static::assertTrue(DeclarationSamples::later(-60)->equals($status->lastAttemptAt() ?? $now));
    }

    public function testOutcomeUnknownStoresTheReasonAndTheErrors(): void
    {
        $given = [DeclarationSamples::submissionError('NEW')];
        $result = DeclarationSamples::result();
        $submitting = new DeclarationStatus(DeclarationState::Submitting, 2, DeclarationSamples::later(-60), [DeclarationSamples::submissionError('OLD')], $result->declarationId(), $result, null, DeclarationSamples::later(-30));
        $now = DeclarationSamples::now();

        $status = $submitting->outcomeUnknown(OutcomeUnknownReason::StaleSubmission, $given, $now);

        static::assertSame(DeclarationState::OutcomeUnknown, $status->state());
        static::assertSame($given, $status->errors());
        static::assertSame(OutcomeUnknownReason::StaleSubmission, $status->outcomeUnknownReason());
        static::assertSame($result->declarationId(), $status->declarationId());
        static::assertSame($result, $status->result());
        static::assertSame($now, $status->updatedAt());
        static::assertSame(2, $status->attempts());
        static::assertTrue(DeclarationSamples::later(-60)->equals($status->lastAttemptAt() ?? $now));
    }

    public function testCredentialsRejectedClearsTheErrorsAndKeepsTheRest(): void
    {
        $result = DeclarationSamples::result();
        $submitting = new DeclarationStatus(DeclarationState::Submitting, 2, DeclarationSamples::later(-60), [DeclarationSamples::submissionError('OLD')], $result->declarationId(), $result, null, DeclarationSamples::later(-30));
        $now = DeclarationSamples::now();

        $status = $submitting->credentialsRejected($now);

        static::assertSame(DeclarationState::InvalidCredentials, $status->state());
        static::assertSame([], $status->errors());
        static::assertSame($result->declarationId(), $status->declarationId());
        static::assertSame($result, $status->result());
        static::assertNull($status->outcomeUnknownReason());
        static::assertSame($now, $status->updatedAt());
        static::assertSame(2, $status->attempts());
    }

    /**
     * @return iterable<string, array{DeclarationState}>
     */
    public static function provideRetriedStates(): iterable
    {
        yield 'error' => [DeclarationState::Error];
        yield 'invalid credentials' => [DeclarationState::InvalidCredentials];
    }

    #[DataProvider('provideRetriedStates')]
    public function testRetriedKeepsTheErrorsAndClearsTheReason(DeclarationState $from): void
    {
        $now = DeclarationSamples::now();
        $status = self::requeueable($from, null);

        self::assertRequeued($status->retried($now), $now);
    }

    public function testResolvedAsNotReceivedKeepsTheErrorsAndClearsTheReason(): void
    {
        $now = DeclarationSamples::now();
        $status = self::requeueable(DeclarationState::OutcomeUnknown, OutcomeUnknownReason::StaleSubmission);

        self::assertRequeued($status->resolvedAsNotReceived($now), $now);
    }

    private static function requeueable(DeclarationState $from, ?OutcomeUnknownReason $reason): DeclarationStatus
    {
        $result = DeclarationSamples::result();

        return new DeclarationStatus(
            $from,
            2,
            DeclarationSamples::later(-60),
            [DeclarationSamples::submissionError('KEPT')],
            $result->declarationId(),
            $result,
            $reason,
            DeclarationSamples::later(-30),
        );
    }

    private static function assertRequeued(DeclarationStatus $pending, Instant $now): void
    {
        $result = DeclarationSamples::result();

        static::assertSame(DeclarationState::Pending, $pending->state());
        static::assertCount(1, $pending->errors());
        static::assertSame('KEPT', $pending->errors()[0]->code());
        static::assertSame($result->declarationId()->value(), $pending->declarationId()?->value());
        static::assertSame($result->declarationId()->value(), $pending->result()?->declarationId()->value());
        static::assertNull($pending->outcomeUnknownReason());
        static::assertSame($now, $pending->updatedAt());
        static::assertSame(2, $pending->attempts());
        static::assertTrue(DeclarationSamples::later(-60)->equals($pending->lastAttemptAt() ?? $now));
    }

    public function testAnUnknownOutcomeIsNotRetried(): void
    {
        $status = DeclarationSamples::statusIn(DeclarationState::OutcomeUnknown, [], null, null);

        try {
            $status->retried(DeclarationSamples::now());
            static::fail('Expected a refused transition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('outcome_unknown', $exception->from());
            static::assertSame('pending', $exception->to());
        }
    }

    public function testNotReceivedIsOnlyForAnUnknownOutcome(): void
    {
        try {
            DeclarationSamples::statusIn(DeclarationState::Error, [], null, null)->resolvedAsNotReceived(DeclarationSamples::now());
            static::fail('Expected a refused transition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('error', $exception->from());
            static::assertSame('pending', $exception->to());
        }
    }

    public function testDeclaredIsOnlyForASubmittingStatusAndResolvedOnlyForAnUnknownOutcome(): void
    {
        try {
            DeclarationSamples::statusIn(DeclarationState::OutcomeUnknown, [], null, null)->declared(DeclarationSamples::result(), DeclarationSamples::now());
            static::fail('Expected a refused transition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('outcome_unknown', $exception->from());
            static::assertSame('declared', $exception->to());
        }
        try {
            DeclarationSamples::statusIn(DeclarationState::Submitting, [], null, null)->resolvedAsDeclared(DeclarationId::fromString('x'), DeclarationSamples::now());
            static::fail('Expected a refused transition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('submitting', $exception->from());
            static::assertSame('declared', $exception->to());
        }
    }

    public function testTransitionsAreCheckedByTheStateMachine(): void
    {
        $now = DeclarationSamples::now();
        $pending = DeclarationSamples::statusIn(DeclarationState::Pending, [], null, null);

        $this->expectException(InvalidStateTransition::class);

        $pending->declared(DeclarationSamples::result(), $now);
    }

    public function testEveryCommandRefusesFromAFinalState(): void
    {
        $now = DeclarationSamples::now();
        $rejected = DeclarationSamples::statusIn(DeclarationState::Rejected, [], null, null);

        self::assertRefusedFromRejected(static fn(): DeclarationStatus => $rejected->submissionStarted($now));
        self::assertRefusedFromRejected(static fn(): DeclarationStatus => $rejected->rejected([], $now));
        self::assertRefusedFromRejected(static fn(): DeclarationStatus => $rejected->alreadyDeclared([], $now));
        self::assertRefusedFromRejected(static fn(): DeclarationStatus => $rejected->failed([], $now));
        self::assertRefusedFromRejected(static fn(): DeclarationStatus => $rejected->outcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], $now));
        self::assertRefusedFromRejected(static fn(): DeclarationStatus => $rejected->credentialsRejected($now));
        self::assertRefusedFromRejected(static fn(): DeclarationStatus => $rejected->retried($now));
        self::assertRefusedFromRejected(static fn(): DeclarationStatus => $rejected->resolvedAsNotReceived($now));
        self::assertRefusedFromRejected(static fn(): DeclarationStatus => $rejected->resolvedAsDeclared(DeclarationId::fromString('x'), $now));
    }

    /**
     * @param Closure(): DeclarationStatus $call
     *
     * @param-immediately-invoked-callable $call
     */
    private static function assertRefusedFromRejected(Closure $call): void
    {
        $exception = DomainAssertions::assertViolationCode('declaration_record.invalid_state_transition', $call);

        static::assertInstanceOf(InvalidStateTransition::class, $exception);
        static::assertSame('rejected', $exception->from());
    }

}
