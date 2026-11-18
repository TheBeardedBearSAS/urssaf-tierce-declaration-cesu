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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Moves the declarations refused for invalid credentials back to pending (once the tenant credentials are fixed).
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class RequeueAfterInvalidCredentials
{
    public function __construct(public TenantId $tenantId, public ?int $limit) {}
}
