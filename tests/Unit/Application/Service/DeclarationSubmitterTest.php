<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationSubmissionFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\UnexpectedGatewayFailure;
use Throwable;

/**
 * One test per row of the outcome table of the submitter, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class DeclarationSubmitterTest extends TestCase
{
    public function testADeclaredSalaryIsStoredWithItsResult(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(Harness::result(1));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('declared', $submission->declaration->status->state);
        static::assertSame(1, $submission->declaration->status->attempts);
        static::assertSame('209299500001', $submission->declaration->status->declarationId);
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit', 'publish:DeclarationDeclared'],
            $harness->journal->entries(),
        );
        static::assertSame(['tenant-1'], $harness->gateway->tenants());
        static::assertEquals([$harness->declaration()], $harness->gateway->declarations());
        $stored = $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'));
        static::assertSame(DeclarationState::Declared, $stored->state());
        static::assertSame('2026-11-15T10:00:00Z', $stored->updatedAt()->toIso8601());
    }

    public function testALostClaimLeavesTheRecordAloneAndSendsNothing(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->declarations->refuseClaim(DeclarationRecordId::fromString('record-1'));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::NotClaimed, $submission->outcome);
        static::assertSame('record-1', $submission->declaration->recordId);
        static::assertSame('pending', $submission->declaration->status->state);
        static::assertSame([], $harness->journal->entries());
        static::assertSame([], $harness->gateway->calls());
    }

    public function testAnAlreadySubmittingRecordIsNotClaimed(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Submitting);

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::NotClaimed, $submission->outcome);
        static::assertSame('submitting', $submission->declaration->status->state);
        static::assertSame([], $harness->gateway->calls());
    }

    public function testAServerErrorLeavesTheOutcomeUnknownWithItsErrors(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(OutcomeUnknown::fromUnavailable(UrssafUnavailable::withStatus(
            Operation::DeclareSalary,
            500,
            null,
            ApiErrors::of(ApiError::of('ERR_TECHNIQUE', 'Technical error', 'detail')),
        )));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::OutcomeUnknown, $submission->outcome);
        static::assertSame('outcome_unknown', $submission->declaration->status->state);
        static::assertSame('may_have_reached_urssaf', $submission->declaration->status->outcomeUnknownReason);
        static::assertSame(
            [['code' => 'ERR_TECHNIQUE', 'message' => 'Technical error', 'description' => 'detail']],
            $submission->declaration->status->errors,
        );
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit', 'publish:DeclarationOutcomeUnknown'],
            $harness->journal->entries(),
        );
    }

    public function testAnUnreadableAnswerLeavesTheOutcomeUnknown(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(OutcomeUnknown::afterUnreadableResponse(
            Operation::DeclareSalary,
            200,
            new UnexpectedGatewayFailure('unreadable'),
        ));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::OutcomeUnknown, $submission->outcome);
        static::assertSame([], $submission->declaration->status->errors);
    }

    public function testAServerUnavailabilityThatMayHaveReachedUrssafLeavesTheOutcomeUnknown(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(UrssafUnavailable::withStatus(
            Operation::DeclareSalary,
            503,
            null,
            ApiErrors::of(ApiError::of('ERR_TECHNIQUE', 'x', null)),
        ));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::OutcomeUnknown, $submission->outcome);
        static::assertSame('outcome_unknown', $submission->declaration->status->state);
        static::assertCount(1, $submission->declaration->status->errors);
    }

    public function testATransportErrorLeavesTheOutcomeUnknown(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(UrssafUnavailable::dueToTransportError(Operation::DeclareSalary, new RuntimeException('timeout')));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::OutcomeUnknown, $submission->outcome);
        static::assertSame([], $submission->declaration->status->errors);
    }

    public function testADuplicateDeclarationIsRecordedAsAlreadyDeclared(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(self::rejection(
            ErrorKind::Business,
            BusinessError::DuplicateDeclaration,
            ApiErrors::of(ApiError::of('ERR_METIER', 'Already there', 'description')),
        ));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::AlreadyDeclared, $submission->outcome);
        static::assertSame('already_declared', $submission->declaration->status->state);
        static::assertSame(
            [['code' => 'ERR_METIER', 'message' => 'Already there', 'description' => 'description']],
            $submission->declaration->status->errors,
        );
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit', 'publish:DeclarationAlreadyDeclared'],
            $harness->journal->entries(),
        );
    }

    public function testAnotherRejectionIsFinal(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(self::rejection(
            ErrorKind::MandateMissing,
            null,
            ApiErrors::of(ApiError::of('ERR_METIER', 'No mandate', null), ApiError::of('ERR_METIER', 'Other', null)),
        ));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::Rejected, $submission->outcome);
        static::assertSame('rejected', $submission->declaration->status->state);
        static::assertCount(2, $submission->declaration->status->errors);
        static::assertSame('No mandate', $submission->declaration->status->errors[0]['message']);
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit', 'publish:DeclarationRejected'],
            $harness->journal->entries(),
        );
    }

    public function testARejectionWithoutDetailsGetsAGenericError(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(self::rejection(ErrorKind::Unknown, null, ApiErrors::none()));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::Rejected, $submission->outcome);
        static::assertSame(
            [[
                'code' => 'urssaf.rejected',
                'message' => 'URSSAF refused the declaration without error details.',
                'description' => null,
            ]],
            $submission->declaration->status->errors,
        );
    }

    public function testRefusedCredentialsAreRecorded(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(UrssafAuthenticationFailed::withStatus(401));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::InvalidCredentials, $submission->outcome);
        static::assertSame('invalid_credentials', $submission->declaration->status->state);
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit', 'publish:DeclarationCredentialsRejected'],
            $harness->journal->entries(),
        );
    }

    public function testAThrottledCallPutsTheRecordBackToPendingAndThrowsWithItsDelay(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $failure = UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, 42, ApiErrors::none());
        $harness->gateway->willDeclare($failure);

        try {
            $this->submit($harness);
            static::fail('Expected RetryableUrssafFailure.');
        } catch (RetryableUrssafFailure $retry) {
            static::assertSame($failure, $retry->getPrevious());
            static::assertSame(42, $retry->retryAfterSeconds());
        }

        $stored = $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'));
        static::assertSame(DeclarationState::Pending, $stored->state());
        static::assertSame(1, $stored->attempts());
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit', 'publish:DeclarationSubmissionFailed,DeclarationRequeued'],
            $this->journalWithMergedPublications($harness),
        );
    }

    public function testTheErrorOfAThrottledCallIsKeptOnTheFailedEvent(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, null, ApiErrors::none()));

        try {
            $this->submit($harness);
            static::fail('Expected RetryableUrssafFailure.');
        } catch (RetryableUrssafFailure $retry) {
            static::assertNull($retry->retryAfterSeconds());
        }

        $failed = $harness->events->events()[0];
        static::assertInstanceOf(DeclarationSubmissionFailed::class, $failed);
        static::assertCount(1, $failed->errors());
        static::assertSame('urssaf.unavailable', $failed->errors()[0]->code());
        static::assertSame('URSSAF method 050 is temporarily unavailable (HTTP 429).', $failed->errors()[0]->message());
        static::assertNull($failed->errors()[0]->description());
    }

    public function testTheBodyErrorsOfAnUnavailabilityAreKept(): void
    {
        $harness = new Harness(DeclarationPolicies::maxAttempts(1));
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(UrssafUnavailable::withStatus(
            Operation::DeclareSalary,
            429,
            null,
            ApiErrors::of(ApiError::of('ERR_X', 'Too many', 'desc')),
        ));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::Exhausted, $submission->outcome);
        static::assertSame(
            [['code' => 'ERR_X', 'message' => 'Too many', 'description' => 'desc']],
            $submission->declaration->status->errors,
        );
    }

    public function testALocalRateLimitRefusalIsRetryable(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(RateLimitExceeded::forOperation(Operation::DeclareSalary, 9));

        try {
            $this->submit($harness);
            static::fail('Expected RetryableUrssafFailure.');
        } catch (RetryableUrssafFailure $retry) {
            static::assertSame(9, $retry->retryAfterSeconds());
        }

        $stored = $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'));
        static::assertSame(DeclarationState::Pending, $stored->state());
        static::assertSame('urssaf.unavailable', $stored->errors()[0]->code());
        static::assertSame(RateLimitExceeded::forOperation(Operation::DeclareSalary, 9)->getMessage(), $stored->errors()[0]->message());
    }

    public function testTheLastAttemptLeavesTheRecordInErrorAndReturnsExhausted(): void
    {
        $harness = new Harness(DeclarationPolicies::maxAttempts(1));
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, 5, ApiErrors::none()));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::Exhausted, $submission->outcome);
        static::assertSame('error', $submission->declaration->status->state);
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit', 'publish:DeclarationSubmissionFailed'],
            $harness->journal->entries(),
        );
    }

    public function testTheMaximumIsComparedWithTheAttemptsMade(): void
    {
        $harness = new Harness(DeclarationPolicies::maxAttempts(2));
        $record = $harness->record(1, DeclarationState::Error, 1);
        $record->retry(Harness::at(Harness::NOW), 2);
        $harness->declarations->add($record);
        $harness->gateway->willDeclare(UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, null, ApiErrors::none()));

        $submission = $this->submit($harness);

        static::assertSame(ItemOutcome::Exhausted, $submission->outcome);
        static::assertSame(2, $submission->declaration->status->attempts);
    }

    public function testASecondAttemptStillLeavesRoomForAnotherWhenTheMaximumIsThree(): void
    {
        $harness = new Harness(DeclarationPolicies::maxAttempts(3));
        $record = $harness->record(1, DeclarationState::Error, 1);
        $record->retry(Harness::at(Harness::NOW), 3);
        $harness->declarations->add($record);
        $harness->gateway->willDeclare(UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, null, ApiErrors::none()));

        $this->expectException(RetryableUrssafFailure::class);

        $this->submit($harness);
    }

    public function testAnotherPackageExceptionIsRethrownAfterTheRecordIsRequeued(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $failure = new UnexpectedGatewayFailure('HTTP 404');
        $harness->gateway->willDeclare($failure);

        try {
            $this->submit($harness);
            static::fail('Expected the failure.');
        } catch (Throwable $thrown) {
            static::assertSame($failure, $thrown);
        }

        $stored = $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'));
        static::assertSame(DeclarationState::Pending, $stored->state());
        static::assertSame(1, $stored->attempts());
        $error = $stored->errors()[0];
        static::assertSame('urssaf.unexpected_response', $error->code());
        static::assertSame(UnexpectedGatewayFailure::class, $error->message());
        static::assertNull($error->description());
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit'],
            \array_slice($harness->journal->entries(), 0, 5),
        );
    }

    public function testAnotherPackageExceptionOnTheLastAttemptLeavesTheRecordInError(): void
    {
        $harness = new Harness(DeclarationPolicies::maxAttempts(1));
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(new UnexpectedGatewayFailure('HTTP 404'));

        try {
            $this->submit($harness);
            static::fail('Expected the failure.');
        } catch (UnexpectedGatewayFailure $thrown) {
            static::assertSame('HTTP 404', $thrown->getMessage());
        }

        static::assertSame(
            DeclarationState::Error,
            $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'))->state(),
        );
    }

    public function testAnUnexpectedFailureLeavesTheOutcomeUnknownAndIsRethrown(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $failure = new LogicException('bug');
        $harness->gateway->willDeclare($failure);

        try {
            $this->submit($harness);
            static::fail('Expected the failure.');
        } catch (Throwable $thrown) {
            static::assertSame($failure, $thrown);
        }

        $stored = $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'));
        static::assertSame(DeclarationState::OutcomeUnknown, $stored->state());
        static::assertSame(OutcomeUnknownReason::MayHaveReachedUrssaf, $stored->outcomeUnknownReason());
        $error = $stored->errors()[0];
        static::assertSame('urssaf.unexpected_failure', $error->code());
        static::assertSame(LogicException::class, $error->message());
        static::assertNull($error->description());
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit', 'publish:DeclarationOutcomeUnknown'],
            $harness->journal->entries(),
        );
    }

    private function submit(Harness $harness): DeclarationSubmission
    {
        return (new DeclarationSubmitter(
            $harness->declarations,
            $harness->gateway,
            $harness->clock,
            $harness->unitOfWork,
            $harness->policy,
        ))->submit(DeclarationRecordId::fromString('record-1'), Harness::tenant(), $harness->declaration());
    }

    /**
     * Publications are journaled one per event: merge them to keep the assertion readable.
     *
     * @return list<string>
     */
    private function journalWithMergedPublications(Harness $harness): array
    {
        $merged = [];
        $publications = [];
        foreach ($harness->journal->entries() as $entry) {
            if (str_starts_with($entry, 'publish:')) {
                $publications[] = substr($entry, \strlen('publish:'));

                continue;
            }
            $merged[] = $entry;
        }
        if ($publications !== []) {
            $merged[] = 'publish:' . implode(',', $publications);
        }

        return $merged;
    }

    private static function rejection(ErrorKind $kind, ?BusinessError $business, ApiErrors $errors): UrssafRequestRejected
    {
        return UrssafRequestRejected::withErrors(Operation::DeclareSalary, 400, $errors, $kind, $business);
    }
}
