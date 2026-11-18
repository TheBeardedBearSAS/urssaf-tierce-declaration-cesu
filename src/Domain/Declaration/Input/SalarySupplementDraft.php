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
 * Raw salary supplement (`ComplementSalaire`).
 */
final readonly class SalarySupplementDraft
{
    public function __construct(
        public ?string $category,
        public ?string $subCategory,
        public ?string $amount,
        public ?string $hourlyWage,
        public ?int $hours,
    ) {}
}
