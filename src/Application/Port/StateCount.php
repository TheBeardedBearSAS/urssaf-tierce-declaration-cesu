<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Number of records of one tenant in one state (employer account, mandate or declaration).
 */
final readonly class StateCount
{
    /**
     * @param string $state the value of an EmployerStage, a MandateState or a DeclarationState
     */
    public function __construct(public TenantId $tenantId, public string $state, public int $count) {}
}
