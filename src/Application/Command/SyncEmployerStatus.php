<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Reads the URSSAF status of one tracked employer (method 020), optionally for an employee to read the Cesu+ state of
 * the pair, and applies it to the account and to its active mandate.
 */
final readonly class SyncEmployerStatus
{
    public function __construct(
        public TenantId $tenantId,
        public EmployerSelector $employer,
        #[SensitiveParameter]
        public ?EmployeeReferenceDraft $employee,
    ) {}
}
