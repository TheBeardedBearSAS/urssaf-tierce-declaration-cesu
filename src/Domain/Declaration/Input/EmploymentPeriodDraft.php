<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

/**
 * Raw employment period (`dateDebut`, `dateFin`). An empty string counts as missing.
 */
final readonly class EmploymentPeriodDraft
{
    public function __construct(
        public ?string $startDate,
        public ?string $endDate,
    ) {}
}
