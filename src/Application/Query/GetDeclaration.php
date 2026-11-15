<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Reads one declaration record of a tenant, by record id, external reference or URSSAF declaration id.
 */
final readonly class GetDeclaration
{
    public function __construct(public TenantId $tenantId, public DeclarationSelector $declaration) {}
}
