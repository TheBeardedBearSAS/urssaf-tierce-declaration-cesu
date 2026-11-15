<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Notifies URSSAF that the platform is mandated by a tracked employer (method 030, api-reference §2.2).
 */
final readonly class RegisterMandate
{
    public function __construct(public TenantId $tenantId, public EmployerSelector $employer) {}
}
