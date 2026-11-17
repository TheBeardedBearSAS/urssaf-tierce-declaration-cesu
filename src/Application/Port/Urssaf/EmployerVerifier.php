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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Method 020: reads the status of an employer account (api-reference §2.2). A read, safe to retry; the only polling of the
 * package (ADR 0006).
 */
interface EmployerVerifier
{
    /**
     * Returns the status of the employer account and, when an employee is given, of the Cesu+ link.
     *
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see ErrorKind}, {@see BusinessError})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function verifyEmployer(TenantId $tenantId, #[SensitiveParameter] EmployerVerification $verification): EmployerStatus;
}
