<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Declares a salary to URSSAF (method 050), once per tenant and external reference (ADR 0003, ADR 0006).
 *
 * "skipMandateCheck" opts out of the local mandate guard for this command (ADR 0009).
 */
final readonly class DeclareSalary
{
    public function __construct(
        public TenantId $tenantId,
        public ExternalReference $externalReference,
        #[SensitiveParameter]
        public DeclarationDraft $declaration,
        public bool $skipMandateCheck = false,
    ) {}
}
