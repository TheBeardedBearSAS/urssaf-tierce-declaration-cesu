<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input;

/**
 * Raw identification of an employee for a lookup (methods 020, 021) or a declaration. An empty string counts as
 * missing. Personal data (ADR 0007).
 */
final readonly class EmployeeReferenceDraft
{
    public function __construct(
        public ?string $cesuNumber,
        public ?string $nir,
        public ?EmployeeIdentityDraft $identity,
    ) {}

    /**
     * The administrative data when at least one of their fields is filled in.
     */
    public function filledIdentity(): ?EmployeeIdentityDraft
    {
        return $this->identity instanceof EmployeeIdentityDraft && $this->identity->isFilled() ? $this->identity : null;
    }
}
