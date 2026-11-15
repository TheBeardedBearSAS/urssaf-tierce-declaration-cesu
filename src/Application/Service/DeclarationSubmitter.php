<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service;

use Closure;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\SalaryDeclarer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use Throwable;

/**
 * Claims a pending declaration record, sends the declaration (method 050) and applies the outcome (ADR 0003, ADR 0006).
 *
 * The claim is atomic (compare-and-set pending -> submitting): a concurrent caller gets {@see ItemOutcome::NotClaimed}.
 * The URSSAF call is made outside any transaction; its outcome is then applied in a transaction of its own:
 * - declared, rejected (final), already declared (duplicate), invalid credentials: returned;
 * - 5xx, transport error or unreadable answer ({@see OutcomeUnknown}): outcome unknown, never resent automatically;
 * - failure proven not sent (429, token endpoint, local rate limit): error, then pending again while attempts remain
 *   ({@see RetryableUrssafFailure} thrown after the commit) or {@see ItemOutcome::Exhausted} returned;
 * - any other package exception: error (pending again while attempts remain), rethrown unchanged;
 * - any other failure (a bug, a foreign gateway): outcome unknown (it may have been sent), rethrown unchanged.
 * Messages of port exceptions carry no personal data, so the class name or message is stored as the error.
 */
final readonly class DeclarationSubmitter
{
    public const UNAVAILABLE = 'urssaf.unavailable';
    public const UNEXPECTED_RESPONSE = 'urssaf.unexpected_response';
    public const UNEXPECTED_FAILURE = 'urssaf.unexpected_failure';
    public const REJECTED_WITHOUT_ERRORS = 'urssaf.rejected';

    public function __construct(
        private DeclarationRecordRepository $records,
        private SalaryDeclarer $declarer,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws Throwable {@see RetryableUrssafFailure} when the call provably did not reach URSSAF (the record is back to
     *                   pending, committed); any unexpected failure otherwise, rethrown unchanged after the outcome was
     *                   committed
     */
    public function submit(DeclarationRecordId $id, TenantId $tenantId, #[SensitiveParameter] Declaration $declaration): DeclarationSubmission
    {
        if (!$this->records->claimForSubmission($id, Instant::fromDateTime($this->clock->now()))) {
            return new DeclarationSubmission(DeclarationView::fromRecord($this->records->get($id)), ItemOutcome::NotClaimed);
        }
        $record = $this->records->get($id);
        try {
            $result = $this->declarer->declare($tenantId, $declaration);
        } catch (Throwable $failure) {
            return $this->settleFailure($record, $failure);
        }

        return $this->conclude($record, static function (DeclarationRecord $record, Instant $now) use ($result): ItemOutcome {
            $record->markDeclared($result, $now);

            return ItemOutcome::Declared;
        });
    }

    /**
     * @throws Throwable {@see RetryableUrssafFailure}, or the failure itself when it is neither a business answer nor a
     *                   retryable one
     */
    private function settleFailure(DeclarationRecord $record, Throwable $failure): DeclarationSubmission
    {
        return match (true) {
            $failure instanceof OutcomeUnknown => $this->unknown($record, $failure->errors()->toSubmissionErrors()),
            $failure instanceof UrssafRequestRejected => $this->rejected($record, $failure),
            $failure instanceof UrssafAuthenticationFailed => $this->invalidCredentials($record),
            $failure instanceof UrssafUnavailable && $failure->mayHaveReachedUrssaf() => $this->unknown(
                $record,
                $failure->errors()->toSubmissionErrors(),
            ),
            default => $this->settleUnexpected($record, $failure),
        };
    }

    /**
     * A retryable failure that provably did not reach URSSAF, another package exception, or a failure of unknown origin.
     *
     * @throws Throwable
     */
    private function settleUnexpected(DeclarationRecord $record, Throwable $failure): DeclarationSubmission
    {
        if ($failure instanceof RetryableException) {
            $submission = $this->requeue($record, $this->unavailableErrors($failure));

            return $submission->outcome === ItemOutcome::Exhausted
                ? $submission
                : throw RetryableUrssafFailure::causedBy($failure);
        }
        if ($failure instanceof UrssafException) {
            $this->requeue($record, [SubmissionError::of(self::UNEXPECTED_RESPONSE, $failure::class, null)]);

            throw $failure;
        }
        $this->unknown($record, [SubmissionError::of(self::UNEXPECTED_FAILURE, $failure::class, null)]);

        throw $failure;
    }

    private function invalidCredentials(DeclarationRecord $record): DeclarationSubmission
    {
        return $this->conclude($record, static function (DeclarationRecord $record, Instant $now): ItemOutcome {
            $record->markInvalidCredentials($now);

            return ItemOutcome::InvalidCredentials;
        });
    }

    /**
     * @param list<SubmissionError> $errors
     */
    private function unknown(DeclarationRecord $record, array $errors): DeclarationSubmission
    {
        return $this->conclude($record, static function (DeclarationRecord $record, Instant $now) use ($errors): ItemOutcome {
            $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, $errors, $now);

            return ItemOutcome::OutcomeUnknown;
        });
    }

    /**
     * A duplicate declaration is final too, but it is a state of its own: URSSAF holds the declaration.
     */
    private function rejected(DeclarationRecord $record, UrssafRequestRejected $rejection): DeclarationSubmission
    {
        return $this->conclude($record, static function (DeclarationRecord $record, Instant $now) use ($rejection): ItemOutcome {
            $errors = $rejection->errors()->toSubmissionErrors();
            if ($rejection->isDuplicateDeclaration()) {
                $record->markAlreadyDeclared($errors, $now);

                return ItemOutcome::AlreadyDeclared;
            }
            $record->markRejected(
                $errors === []
                    ? [SubmissionError::of(self::REJECTED_WITHOUT_ERRORS, 'URSSAF refused the declaration without error details.', null)]
                    : $errors,
                $now,
            );

            return ItemOutcome::Rejected;
        });
    }

    /**
     * submitting -> error, then pending again while attempts remain. {@see ItemOutcome::Exhausted} when they do not
     * (the record is left in error), {@see ItemOutcome::Failed} when it was requeued.
     *
     * @param list<SubmissionError> $errors
     */
    private function requeue(DeclarationRecord $record, array $errors): DeclarationSubmission
    {
        return $this->conclude($record, function (DeclarationRecord $record, Instant $now) use ($errors): ItemOutcome {
            $record->markFailed($errors, $now);
            $maxAttempts = $this->policy->maxSubmissionAttempts();
            if (!$record->canRetry($maxAttempts)) {
                return ItemOutcome::Exhausted;
            }
            $record->retry($now, $maxAttempts);

            return ItemOutcome::Failed;
        });
    }

    /**
     * @return list<SubmissionError>
     */
    private function unavailableErrors(RetryableException $failure): array
    {
        if ($failure instanceof UrssafUnavailable && !$failure->errors()->isEmpty()) {
            return $failure->errors()->toSubmissionErrors();
        }

        return [SubmissionError::of(self::UNAVAILABLE, $failure->getMessage(), null)];
    }

    /**
     * Applies the transition in one transaction (save, events) and describes the record with the outcome it returned.
     *
     * @param Closure(DeclarationRecord, Instant): ItemOutcome $transition
     * @param-immediately-invoked-callable $transition
     */
    private function conclude(DeclarationRecord $record, Closure $transition): DeclarationSubmission
    {
        $outcome = $this->unitOfWork->run(function (RecordedEvents $events) use ($record, $transition): ItemOutcome {
            $outcome = $transition($record, Instant::fromDateTime($this->clock->now()));
            $this->records->save($record);
            $events->record(...$record->releaseEvents());

            return $outcome;
        });

        return new DeclarationSubmission(DeclarationView::fromRecord($record), $outcome);
    }
}
