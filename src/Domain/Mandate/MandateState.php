<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;

/**
 * State of a {@see Mandate} (ADR 0006): notified (method 030 succeeded), confirmed (method 020 reports
 * "mandatPresent") or cancelled (method 031 succeeded, final).
 *
 * A refresh of a confirmed mandate is not a transition.
 */
enum MandateState: string
{
    case Notified = 'notified';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Notified => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @throws InvalidStateTransition "mandate.invalid_state_transition"
     */
    public function transitionTo(self $target): self
    {
        if (!$this->canTransitionTo($target)) {
            throw InvalidStateTransition::between('mandate', $this->value, $target->value);
        }

        return $target;
    }

    /**
     * Not cancelled.
     */
    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
