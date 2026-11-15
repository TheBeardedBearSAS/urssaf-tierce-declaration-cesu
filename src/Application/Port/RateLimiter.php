<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Client-side throttling of the global URSSAF quota (2 500 calls per hour in production, all operations together, per
 * tenant; token calls are not counted), ADR 0011.
 *
 * The operation is informative (logs, messages): implementations must not keep per-operation quotas.
 */
interface RateLimiter
{
    /**
     * Reserves one call slot, blocking until one is available or throwing when the implementation is non-blocking.
     *
     * @throws RateLimitExceeded
     */
    public function acquire(TenantId $tenantId, Operation $operation): void;
}
