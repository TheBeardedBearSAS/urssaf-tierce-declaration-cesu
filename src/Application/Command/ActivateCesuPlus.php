<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Activates the Cesu+ service for an employer and one of its employees (method 060, api-reference §2.6).
 *
 * The local mandate guard applies unless the command opts out (ADR 0009).
 */
final readonly class ActivateCesuPlus
{
    public function __construct(
        public TenantId $tenantId,
        #[SensitiveParameter]
        public Employer $employer,
        public CesuNumber $employeeCesuNumber,
        public bool $employeeAgreement,
        public bool $skipMandateCheck = false,
    ) {}
}
