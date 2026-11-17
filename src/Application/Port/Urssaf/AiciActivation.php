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

/**
 * Answer of method 070 (`OutputActivationAici`, api-reference §2.9): `activationAici`.
 */
final readonly class AiciActivation
{
    private function __construct(private bool $active) {}

    public static function of(bool $active): self
    {
        return new self($active);
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
