<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input;

/**
 * Raw address of an employer or an employee, as received from the host (api-reference §2.1 "Adresse"). An empty
 * string counts as missing. Personal data (ADR 0007).
 */
final readonly class AddressDraft
{
    public function __construct(
        public ?int $streetNumber,
        public ?string $streetLetter,
        public ?string $streetType,
        public ?string $streetName,
        public ?string $complement,
        public ?string $locality,
        public ?string $municipalityCode,
        public ?string $postalCode,
    ) {}
}
