<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use DateInterval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationCredentialsRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRecorded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRequeued;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationSubmissionFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Aggregate root: local follow-up of the submission of one salary declaration (050) to URSSAF (ADR 0003, ADR 0006).
 *
 * Created with {@see self::record()}. The identity (id, tenant, external reference, snapshot, creation instant) is
 * immutable; the mutable part is the {@see DeclarationStatus} (state machine: {@see DeclarationState}). The record
 * never holds the declaration payload nor personal data (ADR 0007): a retry is made by the host sending the same
 * declaration again, whose fingerprint is compared with {@see self::hasSameContentAs()}. Every state change records a
 * domain event, see {@see self::releaseEvents()}. Invalid transitions throw {@see InvalidStateTransition}
 * ("declaration_record.invalid_state_transition"); the state and the events are then left untouched.
 *
 * Submission attempts are counted (pending -> submitting); {@see self::retry()} refuses to requeue a failed
 * submission once the maximum number of attempts is reached.
 *
 * Not final only because Doctrine generates lazy-loading proxies by extending the mapped entities: final by
 * convention, never extend it. For the same reason the identifier is neither readonly nor private: a proxy is created
 * with its identifier, which Doctrine assigns again (another instance of the same value) when the proxy is loaded. It is
 * never reassigned otherwise.
 *
 * The root exposes its identity, transitions and queries, hence the method count.
 *
 * @final
 *
 * @mago-expect lint:too-many-methods
 */
class DeclarationRecord
{
    /**
     * Default maximum number of submission attempts before {@see self::retry()} refuses to requeue.
     */
    public const DEFAULT_MAX_ATTEMPTS = 5;

    private DeclarationStatus $status;

    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        protected DeclarationRecordId $id,
        private readonly TenantId $tenantId,
        private readonly ExternalReference $externalReference,
        private readonly DeclarationSnapshot $snapshot,
        private readonly Instant $createdAt,
    ) {
        $this->status = new DeclarationStatus(DeclarationState::Pending, 0, null, [], null, null, null, $createdAt);
    }

    /**
     * Records the declaration (state pending) and records {@see DeclarationRecorded}.
     */
    public static function record(
        DeclarationRecordId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        DeclarationSnapshot $snapshot,
        Instant $now,
    ): self {
        $record = new self($id, $tenantId, $externalReference, $snapshot, $now);
        $record->events[] = new DeclarationRecorded($id, $tenantId, $externalReference, $snapshot->employerCesuNumber(), $now);

        return $record;
    }

    public function id(): DeclarationRecordId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    public function snapshot(): DeclarationSnapshot
    {
        return $this->snapshot;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function status(): DeclarationStatus
    {
        return $this->status;
    }

    public function state(): DeclarationState
    {
        return $this->status->state();
    }

    public function attempts(): int
    {
        return $this->status->attempts();
    }

    public function lastAttemptAt(): ?Instant
    {
        return $this->status->lastAttemptAt();
    }

    /**
     * @return list<SubmissionError>
     */
    public function errors(): array
    {
        return $this->status->errors();
    }

    public function declarationId(): ?DeclarationId
    {
        return $this->status->declarationId();
    }

    public function result(): ?DeclarationResult
    {
        return $this->status->result();
    }

    public function outcomeUnknownReason(): ?OutcomeUnknownReason
    {
        return $this->status->outcomeUnknownReason();
    }

    public function updatedAt(): Instant
    {
        return $this->status->updatedAt();
    }

    /**
     * pending -> submitting: one more attempt, started now. Concurrent workers should rather use
     * {@see DeclarationRecordRepository::claimForSubmission()}.
     *
     * @throws InvalidStateTransition
     */
    public function startSubmission(Instant $now): void
    {
        $this->status = $this->status->submissionStarted($now);
    }

    /**
     * submitting -> declared (050 succeeded). Clears the last errors.
     *
     * @throws InvalidStateTransition
     */
    public function markDeclared(DeclarationResult $result, Instant $now): void
    {
        $this->status = $this->status->declared($result, $now);
        $this->events[] = new DeclarationDeclared($this->id, $this->tenantId, $this->externalReference, $result->declarationId(), $now);
    }

    /**
     * submitting -> rejected (business or validation refusal, final).
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function markRejected(array $errors, Instant $now): void
    {
        $this->status = $this->status->rejected($errors, $now);
        $this->events[] = new DeclarationRejected($this->id, $this->tenantId, $this->externalReference, $errors, $now);
    }

    /**
     * submitting -> already_declared (duplicate ERR_METIER, final).
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function markAlreadyDeclared(array $errors, Instant $now): void
    {
        $this->status = $this->status->alreadyDeclared($errors, $now);
        $this->events[] = new DeclarationAlreadyDeclared($this->id, $this->tenantId, $this->externalReference, $now);
    }

    /**
     * submitting -> outcome_unknown (transport error or 5xx on 050, or a stale submission): the request may have reached
     * URSSAF, so it is never resent automatically (ADR 0003).
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function markOutcomeUnknown(OutcomeUnknownReason $reason, array $errors, Instant $now): void
    {
        $this->status = $this->status->outcomeUnknown($reason, $errors, $now);
        $this->events[] = new DeclarationOutcomeUnknown($this->id, $this->tenantId, $this->externalReference, $reason, $now);
    }

    /**
     * submitting -> error: a retryable failure proven not sent (429, local rate limit).
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function markFailed(array $errors, Instant $now): void
    {
        $this->status = $this->status->failed($errors, $now);
        $this->events[] = new DeclarationSubmissionFailed($this->id, $this->tenantId, $errors, $now);
    }

    /**
     * submitting -> invalid_credentials. Clears the last errors.
     *
     * @throws InvalidStateTransition
     */
    public function markInvalidCredentials(Instant $now): void
    {
        $this->status = $this->status->credentialsRejected($now);
        $this->events[] = new DeclarationCredentialsRejected($this->id, $this->tenantId, $now);
    }

    /**
     * error -> pending (while attempts remain) or invalid_credentials -> pending (always). A record with an unknown
     * outcome is not retried: see {@see self::resolveAsNotReceived()}.
     *
     * @param positive-int $maxAttempts
     *
     * @throws InvariantViolation     "declaration_record.max_attempts_reached"
     * @throws InvalidStateTransition
     */
    public function retry(Instant $now, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): void
    {
        if (!$this->canRetry($maxAttempts)) {
            throw InvariantViolation::because(
                'declaration_record.max_attempts_reached',
                'The maximum number of submission attempts is reached.',
                ['attempts' => $this->status->attempts(), 'max_attempts' => $maxAttempts],
            );
        }
        $from = $this->status->state();
        $this->status = $this->status->retried($now);
        $this->events[] = new DeclarationRequeued($this->id, $this->tenantId, $from, $now);
    }

    /**
     * False only for a failed submission (state error) that used all its attempts.
     *
     * @param positive-int $maxAttempts
     */
    public function canRetry(int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): bool
    {
        return $this->status->state() !== DeclarationState::Error || $this->status->hasAttemptsLeft($maxAttempts);
    }

    /**
     * outcome_unknown -> declared: the operator found the declaration on cesu.urssaf.fr (no amounts).
     *
     * @throws InvalidStateTransition
     */
    public function resolveAsDeclared(DeclarationId $declarationId, Instant $now): void
    {
        $this->status = $this->status->resolvedAsDeclared($declarationId, $now);
        $this->events[] = new DeclarationDeclared($this->id, $this->tenantId, $this->externalReference, $declarationId, $now);
    }

    /**
     * outcome_unknown -> pending: the operator checked that URSSAF did not receive the declaration.
     *
     * @throws InvalidStateTransition
     */
    public function resolveAsNotReceived(Instant $now): void
    {
        $from = $this->status->state();
        $this->status = $this->status->resolvedAsNotReceived($now);
        $this->events[] = new DeclarationRequeued($this->id, $this->tenantId, $from, $now);
    }

    /**
     * True when the record is submitting and its last attempt started at or before now - staleAfter.
     */
    public function isStaleSubmission(Instant $now, DateInterval $staleAfter): bool
    {
        return $this->status->state() === DeclarationState::Submitting
            && $this->status->isLastAttemptStartedBefore($now, $staleAfter);
    }

    /**
     * Idempotency: true when the snapshot has the same content fingerprint as the recorded declaration.
     */
    public function hasSameContentAs(DeclarationSnapshot $snapshot): bool
    {
        return $this->snapshot->hasSameContentAs($snapshot);
    }

    /**
     * Returns the events recorded since the last call and forgets them.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
