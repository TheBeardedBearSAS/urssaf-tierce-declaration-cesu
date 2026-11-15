<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Reads a tracked employer account with its mandate, from the local store (no URSSAF call).
 */
final readonly class GetEmployerAccount
{
    public function __construct(public TenantId $tenantId, public EmployerSelector $employer) {}
}
