<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Cancels the mandate of a tracked employer with URSSAF (method 031, api-reference §2.3).
 */
final readonly class CancelMandate
{
    public function __construct(public TenantId $tenantId, public EmployerSelector $employer) {}
}
