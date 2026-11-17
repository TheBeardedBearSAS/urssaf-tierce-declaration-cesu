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

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Method 040: simulates the contributions of a salary (api-reference §2.6). A read (nothing is stored), safe to retry.
 */
interface ContributionEstimator
{
    /**
     * Returns the simulated contributions.
     *
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see ErrorKind}, {@see BusinessError})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function estimate(TenantId $tenantId, EstimateRequest $request): ContributionsEstimate;
}
