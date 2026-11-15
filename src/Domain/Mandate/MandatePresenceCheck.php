<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Last answer of method 020 about the mandate: "mandatPresent" and when it was read (api-reference §4.10).
 */
final readonly class MandatePresenceCheck
{
    private function __construct(
        private bool $mandatePresent,
        private Instant $checkedAt,
    ) {}

    public static function of(bool $mandatePresent, Instant $checkedAt): self
    {
        return new self($mandatePresent, $checkedAt);
    }

    public function mandatePresent(): bool
    {
        return $this->mandatePresent;
    }

    public function checkedAt(): Instant
    {
        return $this->checkedAt;
    }

    public function equals(self $other): bool
    {
        return $this->mandatePresent === $other->mandatePresent && $this->checkedAt->equals($other->checkedAt);
    }
}
