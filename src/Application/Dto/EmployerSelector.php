<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

/**
 * Designates an employer account of a tenant: by account id, by the host external reference or by Cesu number.
 */
final readonly class EmployerSelector
{
    private function __construct(public EmployerAccountId|ExternalReference|CesuNumber $reference) {}

    public static function byAccountId(EmployerAccountId $accountId): self
    {
        return new self($accountId);
    }

    public static function byExternalReference(ExternalReference $externalReference): self
    {
        return new self($externalReference);
    }

    public static function byCesuNumber(CesuNumber $cesuNumber): self
    {
        return new self($cesuNumber);
    }
}
