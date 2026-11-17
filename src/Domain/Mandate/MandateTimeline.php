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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Instants of a {@see Mandate}: notification, confirmation, cancellation and last update.
 */
final readonly class MandateTimeline
{
    private function __construct(
        private Instant $notifiedAt,
        private ?Instant $confirmedAt,
        private ?Instant $cancelledAt,
        private Instant $updatedAt,
    ) {}

    public static function notified(Instant $at): self
    {
        return new self($at, null, null, $at);
    }

    public function notifiedAt(): Instant
    {
        return $this->notifiedAt;
    }

    public function confirmedAt(): ?Instant
    {
        return $this->confirmedAt;
    }

    public function cancelledAt(): ?Instant
    {
        return $this->cancelledAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    public function withConfirmation(Instant $at): self
    {
        return new self($this->notifiedAt, $at, $this->cancelledAt, $at);
    }

    public function withCancellation(Instant $at): self
    {
        return new self($this->notifiedAt, $this->confirmedAt, $at, $at);
    }

    public function touched(Instant $at): self
    {
        return new self($this->notifiedAt, $this->confirmedAt, $this->cancelledAt, $at);
    }

    public function equals(self $other): bool
    {
        return $this->notifiedAt->equals($other->notifiedAt)
            && $this->confirmedAt?->toIso8601() === $other->confirmedAt?->toIso8601()
            && $this->cancelledAt?->toIso8601() === $other->cancelledAt?->toIso8601()
            && $this->updatedAt->equals($other->updatedAt);
    }
}
