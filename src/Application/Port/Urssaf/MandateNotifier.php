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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Methods 030 and 031: registers and cancels the mandate of the platform (api-reference §2.4 and §2.5). Assumed idempotent
 * (api-reference §5), hence safe to retry.
 */
interface MandateNotifier
{
    /**
     * Notifies the mandate. See {@see UrssafRequestRejected::isMandateHeldElsewhere()} and
     * {@see UrssafRequestRejected::isAccountUnknown()}.
     *
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see ErrorKind}, {@see BusinessError}) (e.g. mandate held elsewhere, account unknown)
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function registerMandate(TenantId $tenantId, #[SensitiveParameter] Employer $employer): void;

    /**
     * Cancels the mandate. See {@see UrssafRequestRejected::isMandateHeldElsewhere()} and
     * {@see UrssafRequestRejected::isAccountUnknown()}.
     *
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see ErrorKind}, {@see BusinessError}) (e.g. mandate held elsewhere, account unknown)
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function cancelMandate(TenantId $tenantId, #[SensitiveParameter] Employer $employer): void;
}
