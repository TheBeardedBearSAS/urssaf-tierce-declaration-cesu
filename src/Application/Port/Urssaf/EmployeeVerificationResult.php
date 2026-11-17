<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Answer of method 021 (`OutputVerificationSp`, api-reference §2.3): whether the employee is registered and, when
 * given, the Cesu number. No invariant: the presence of `numeroCesu` when `inscrit` is false is not published
 * (api-reference §5).
 */
final readonly class EmployeeVerificationResult
{
    private function __construct(private bool $registered, private ?CesuNumber $cesuNumber) {}

    public static function of(bool $registered, ?CesuNumber $cesuNumber): self
    {
        return new self($registered, $cesuNumber);
    }

    public function registered(): bool
    {
        return $this->registered;
    }

    public function cesuNumber(): ?CesuNumber
    {
        return $this->cesuNumber;
    }

    public function equals(self $other): bool
    {
        return $this->registered === $other->registered
            && $this->cesuNumber?->value() === $other->cesuNumber?->value();
    }
}
