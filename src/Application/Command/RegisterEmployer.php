<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Registers a new employer with URSSAF (method 010) and tracks the resulting account (ADR 0007, api-reference §2.1).
 *
 * The bank details travel to URSSAF only, they are never stored.
 */
final readonly class RegisterEmployer
{
    public function __construct(
        public TenantId $tenantId,
        public ExternalReference $externalReference,
        #[SensitiveParameter]
        public NewEmployerDraft $employer,
    ) {}
}
