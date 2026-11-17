<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;

/**
 * Lifecycle of a declaration record (ADR 0003, ADR 0006).
 *
 * pending -> submitting -> declared (final unless found afterwards, see below)
 *                       -> rejected | already_declared (final)
 *                       -> outcome_unknown -> declared (manual, idDeclaration found) | pending (manual, not received)
 *                       -> error (proven not sent) | invalid_credentials -> pending (retry)
 */
enum DeclarationState: string
{
    case Pending = 'pending';
    case Submitting = 'submitting';
    case Declared = 'declared';
    case Rejected = 'rejected';
    case AlreadyDeclared = 'already_declared';
    case OutcomeUnknown = 'outcome_unknown';
    case Error = 'error';
    case InvalidCredentials = 'invalid_credentials';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Submitting],
            self::Submitting => [
                self::Declared,
                self::Rejected,
                self::AlreadyDeclared,
                self::OutcomeUnknown,
                self::Error,
                self::InvalidCredentials,
            ],
            self::OutcomeUnknown => [self::Declared, self::Pending],
            self::Error, self::InvalidCredentials => [self::Pending],
            self::Declared, self::Rejected, self::AlreadyDeclared => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @throws InvalidStateTransition "declaration_record.invalid_state_transition"
     */
    public function transitionTo(self $target): self
    {
        if (!$this->canTransitionTo($target)) {
            throw InvalidStateTransition::between('declaration_record', $this->value, $target->value);
        }

        return $target;
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
