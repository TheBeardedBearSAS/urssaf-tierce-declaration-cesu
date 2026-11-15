<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Checks whether an employee is registered with Cesu (method 021, api-reference §2.5).
 */
final readonly class VerifyEmployee
{
    public function __construct(
        public TenantId $tenantId,
        #[SensitiveParameter]
        public EmployeeReferenceDraft $employee,
    ) {}
}
