<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Method 070: activates the AICI service of an employer (api-reference §2.9). Never resent automatically.
 */
interface AiciActivator
{
    /**
     * Activates AICI.
     *
     * @throws OutcomeUnknown             a transport error or a 5xx may have reached URSSAF: never resent (ADR 0003)
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see ErrorKind}, {@see BusinessError})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable, thrown only when the call provably did not reach URSSAF (429, token failure)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function activateAici(TenantId $tenantId, #[SensitiveParameter] AiciActivationRequest $request): AiciActivation;
}
