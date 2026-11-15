<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * The commands of a {@see DeclarationRecord}, with the state each one reaches and the states it may be run from.
 */
enum RecordCommand: string
{
    case StartSubmission = 'startSubmission';
    case MarkDeclared = 'markDeclared';
    case MarkRejected = 'markRejected';
    case MarkAlreadyDeclared = 'markAlreadyDeclared';
    case MarkOutcomeUnknown = 'markOutcomeUnknown';
    case MarkFailed = 'markFailed';
    case MarkInvalidCredentials = 'markInvalidCredentials';
    case Retry = 'retry';
    case ResolveAsDeclared = 'resolveAsDeclared';
    case ResolveAsNotReceived = 'resolveAsNotReceived';

    /**
     * @throws InvalidStateTransition
     * @throws InvariantViolation
     */
    public function run(DeclarationRecord $record, Instant $now): void
    {
        match ($this) {
            self::StartSubmission => $record->startSubmission($now),
            self::MarkDeclared => $record->markDeclared(DeclarationSamples::result(), $now),
            self::MarkRejected => $record->markRejected([], $now),
            self::MarkAlreadyDeclared => $record->markAlreadyDeclared([], $now),
            self::MarkOutcomeUnknown => $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], $now),
            self::MarkFailed => $record->markFailed([], $now),
            self::MarkInvalidCredentials => $record->markInvalidCredentials($now),
            self::Retry => $record->retry($now),
            self::ResolveAsDeclared => $record->resolveAsDeclared(DeclarationId::fromString('found'), $now),
            self::ResolveAsNotReceived => $record->resolveAsNotReceived($now),
        };
    }

    public function target(): DeclarationState
    {
        return match ($this) {
            self::StartSubmission => DeclarationState::Submitting,
            self::MarkDeclared, self::ResolveAsDeclared => DeclarationState::Declared,
            self::MarkRejected => DeclarationState::Rejected,
            self::MarkAlreadyDeclared => DeclarationState::AlreadyDeclared,
            self::MarkOutcomeUnknown => DeclarationState::OutcomeUnknown,
            self::MarkFailed => DeclarationState::Error,
            self::MarkInvalidCredentials => DeclarationState::InvalidCredentials,
            self::Retry, self::ResolveAsNotReceived => DeclarationState::Pending,
        };
    }

    /**
     * @return list<DeclarationState>
     */
    public function allowedFrom(): array
    {
        return match ($this) {
            self::StartSubmission => [DeclarationState::Pending],
            self::MarkDeclared,
            self::MarkRejected,
            self::MarkAlreadyDeclared,
            self::MarkOutcomeUnknown,
            self::MarkFailed,
            self::MarkInvalidCredentials => [DeclarationState::Submitting],
            self::Retry => [DeclarationState::Error, DeclarationState::InvalidCredentials],
            self::ResolveAsDeclared, self::ResolveAsNotReceived => [DeclarationState::OutcomeUnknown],
        };
    }
}
