<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

interface CredentialsProvider
{
    /**
     * @throws RecordNotFound "credentials.not_found" when the tenant has no credentials
     */
    public function forTenant(TenantId $tenantId): Credentials;
}
