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

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * GDPR erasure of a tracked employer from the local store: the account, every mandate and every declaration record of
 * the employer (ADR 0015, lot 10 design §5.1). Nothing is sent to URSSAF: cancel the mandate first (CancelMandate) if it
 * must end there.
 *
 * "allowActiveMandate" and "allowUnknownOutcomes" opt out of two refusals: null applies the WorkflowPolicy default, a
 * boolean is an explicit choice of this call. A submission in flight is always refused.
 */
final readonly class ForgetEmployer
{
    public function __construct(
        public TenantId $tenantId,
        public EmployerSelector $employer,
        public ?bool $allowActiveMandate,
        public ?bool $allowUnknownOutcomes,
    ) {}
}
