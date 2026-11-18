<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Counts of the records by state, for every tenant that has records, or for one tenant (ADR 0003).
 */
final readonly class GetWorkflowOverview
{
    private function __construct(public ?TenantId $tenantId) {}

    public static function allTenants(): self
    {
        return new self(null);
    }

    public static function forTenant(TenantId $tenantId): self
    {
        return new self($tenantId);
    }
}
