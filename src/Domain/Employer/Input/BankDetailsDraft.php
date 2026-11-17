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
 * Raw bank details of an employer (api-reference §2.1 "Rib"). An empty string counts as missing. Personal data (ADR
 * 0007).
 */
final readonly class BankDetailsDraft
{
    public function __construct(
        public ?string $iban,
        public ?string $bic,
        public ?bool $sepaMandate,
    ) {}
}
