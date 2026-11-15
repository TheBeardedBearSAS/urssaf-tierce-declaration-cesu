<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Reads the URSSAF status of an employer (method 020), optionally for an employee to read the Cesu+ state of the pair.
 * Pure: nothing is stored, even for a tracked employer (use SyncEmployerStatus to refresh the tracked account).
 */
final readonly class VerifyEmployer
{
    public function __construct(
        public TenantId $tenantId,
        #[SensitiveParameter]
        public Employer $employer,
        #[SensitiveParameter]
        public ?EmployeeReferenceDraft $employee,
    ) {}
}
