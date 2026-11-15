<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use DateInterval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Current status of a {@see DeclarationRecord}: state, submission attempts (count and start of the last one), causes of
 * the last refusal or failure, declaration id and result once declared, reason of an unknown outcome, last update.
 * Immutable; every change goes through the state machine of {@see DeclarationState}.
 *
 * Invariants: the attempt count is not negative; a declared record has its declaration id; an unknown outcome has its
 * reason.
 *
 * The public constructor is the factory (more than 5 parts). One accessor per field and one transition per command, hence
 * the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class DeclarationStatus
{
    private const AGGREGATE = 'declaration_record';

    /**
     * @param list<SubmissionError> $errors
     *
     * @throws InvariantViolation "declaration_record.negative_attempts", "declaration_record.declaration_id_required"
     *                            or "declaration_record.outcome_unknown_reason_required"
     */
    public function __construct(
        private DeclarationState $state,
        private int $attempts,
        private ?Instant $lastAttemptAt,
        private array $errors,
        private ?DeclarationId $declarationId,
        private ?DeclarationResult $result,
        private ?OutcomeUnknownReason $outcomeUnknownReason,
        private Instant $updatedAt,
    ) {
        if ($attempts < 0) {
            throw InvariantViolation::because(
                self::AGGREGATE . '.negative_attempts',
                'The number of submission attempts must not be negative.',
                ['attempts' => $attempts],
            );
        }
        if (!$declarationId instanceof DeclarationId && $state === DeclarationState::Declared) {
            throw InvariantViolation::because(
                self::AGGREGATE . '.declaration_id_required',
                'A declared record requires the declaration id.',
            );
        }
        if (!$outcomeUnknownReason instanceof OutcomeUnknownReason && $state === DeclarationState::OutcomeUnknown) {
            throw InvariantViolation::because(
                self::AGGREGATE . '.outcome_unknown_reason_required',
                'A record with an unknown outcome requires the reason.',
            );
        }
    }

    /**
     * pending -> submitting: one more attempt, started now.
     *
     * @throws InvalidStateTransition
     */
    public function submissionStarted(Instant $now): self
    {
        return new self(
            $this->state->transitionTo(DeclarationState::Submitting),
            $this->attempts + 1,
            $now,
            $this->errors,
            $this->declarationId,
            $this->result,
            null,
            $now,
        );
    }

    /**
     * submitting -> declared: stores the declaration id and the result, clears the errors.
     *
     * @throws InvalidStateTransition
     */
    public function declared(DeclarationResult $result, Instant $now): self
    {
        $this->assertState(DeclarationState::Submitting, DeclarationState::Declared);

        return $this->moved(DeclarationState::Declared, $now, [], $result->declarationId(), $result);
    }

    /**
     * outcome_unknown -> declared: the operator found the declaration; no amounts. Clears the errors.
     *
     * @throws InvalidStateTransition
     */
    public function resolvedAsDeclared(DeclarationId $declarationId, Instant $now): self
    {
        $this->assertState(DeclarationState::OutcomeUnknown, DeclarationState::Declared);

        return $this->moved(DeclarationState::Declared, $now, [], $declarationId, null);
    }

    /**
     * submitting -> rejected (business or validation refusal): stores the errors.
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function rejected(array $errors, Instant $now): self
    {
        return $this->moved(DeclarationState::Rejected, $now, $errors, $this->declarationId, $this->result);
    }

    /**
     * submitting -> already_declared (duplicate): stores the errors.
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function alreadyDeclared(array $errors, Instant $now): self
    {
        return $this->moved(DeclarationState::AlreadyDeclared, $now, $errors, $this->declarationId, $this->result);
    }

    /**
     * submitting -> error (failure proven not sent): stores the errors.
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function failed(array $errors, Instant $now): self
    {
        return $this->moved(DeclarationState::Error, $now, $errors, $this->declarationId, $this->result);
    }

    /**
     * submitting -> outcome_unknown: stores the reason and the errors.
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function outcomeUnknown(OutcomeUnknownReason $reason, array $errors, Instant $now): self
    {
        return new self(
            $this->state->transitionTo(DeclarationState::OutcomeUnknown),
            $this->attempts,
            $this->lastAttemptAt,
            $errors,
            $this->declarationId,
            $this->result,
            $reason,
            $now,
        );
    }

    /**
     * submitting -> invalid_credentials: clears the errors.
     *
     * @throws InvalidStateTransition
     */
    public function credentialsRejected(Instant $now): self
    {
        return $this->moved(DeclarationState::InvalidCredentials, $now, [], $this->declarationId, $this->result);
    }

    /**
     * error or invalid_credentials -> pending: the errors are kept. An unknown outcome is never retried, see
     * {@see self::resolvedAsNotReceived()}.
     *
     * @throws InvalidStateTransition
     */
    public function retried(Instant $now): self
    {
        if ($this->state === DeclarationState::OutcomeUnknown) {
            throw InvalidStateTransition::between(self::AGGREGATE, $this->state->value, DeclarationState::Pending->value);
        }

        return $this->moved(DeclarationState::Pending, $now, $this->errors, $this->declarationId, $this->result);
    }

    /**
     * outcome_unknown -> pending: the operator checked that URSSAF did not receive the declaration. The errors are
     * kept, the reason is cleared.
     *
     * @throws InvalidStateTransition
     */
    public function resolvedAsNotReceived(Instant $now): self
    {
        $this->assertState(DeclarationState::OutcomeUnknown, DeclarationState::Pending);

        return $this->moved(DeclarationState::Pending, $now, $this->errors, $this->declarationId, $this->result);
    }

    public function state(): DeclarationState
    {
        return $this->state;
    }

    /**
     * Number of submission attempts (pending -> submitting) so far.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Start of the last submission attempt; null while never submitted.
     */
    public function lastAttemptAt(): ?Instant
    {
        return $this->lastAttemptAt;
    }

    /**
     * Causes of the last refusal or failure.
     *
     * @return list<SubmissionError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function declarationId(): ?DeclarationId
    {
        return $this->declarationId;
    }

    /**
     * Null while not declared, and when the declaration was found manually (no amounts).
     */
    public function result(): ?DeclarationResult
    {
        return $this->result;
    }

    public function outcomeUnknownReason(): ?OutcomeUnknownReason
    {
        return $this->outcomeUnknownReason;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    /**
     * True while fewer than maxAttempts submission attempts were made.
     */
    public function hasAttemptsLeft(int $maxAttempts): bool
    {
        return $this->attempts < $maxAttempts;
    }

    /**
     * True when the last submission attempt started at or before now - age (false when never started).
     */
    public function isLastAttemptStartedBefore(Instant $now, DateInterval $age): bool
    {
        return $this->lastAttemptAt instanceof Instant
            && !Instant::fromDateTime($this->lastAttemptAt->toDateTimeImmutable()->add($age))->isAfter($now);
    }

    /**
     * @throws InvalidStateTransition when the status is not in the required state
     */
    private function assertState(DeclarationState $required, DeclarationState $target): void
    {
        if ($this->state !== $required) {
            throw InvalidStateTransition::between(self::AGGREGATE, $this->state->value, $target->value);
        }
    }

    /**
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    private function moved(
        DeclarationState $target,
        Instant $now,
        array $errors,
        ?DeclarationId $declarationId,
        ?DeclarationResult $result,
    ): self {
        return new self(
            $this->state->transitionTo($target),
            $this->attempts,
            $this->lastAttemptAt,
            $errors,
            $declarationId,
            $result,
            null,
            $now,
        );
    }
}
