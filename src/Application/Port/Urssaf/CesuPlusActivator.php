<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Method 060: activates Cesu+ for an employer / employee pair (api-reference §2.8). Never resent automatically.
 */
interface CesuPlusActivator
{
    /**
     * Activates Cesu+. See {@see UrssafRequestRejected::isMandateMissing()}.
     *
     * @throws OutcomeUnknown             a transport error or a 5xx may have reached URSSAF: never resent (ADR 0003)
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see ErrorKind}, {@see BusinessError}) (e.g. mandate missing)
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable, thrown only when the call provably did not reach URSSAF (429, token failure)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function activateCesuPlus(TenantId $tenantId, CesuPlusActivationRequest $request): CesuPlusActivation;
}
