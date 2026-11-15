<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Estimates the contributions of a salary (method 040): nothing is stored (api-reference §2.6).
 */
final readonly class EstimateContributions
{
    public function __construct(public TenantId $tenantId, public EstimateRequest $request) {}
}
