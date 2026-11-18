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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Method 050: declares a salary (api-reference §2.7). Never resent automatically (ADR 0003): there is no way to look a
 * declaration up.
 */
interface SalaryDeclarer
{
    /**
     * Declares the salary. {@see UrssafRequestRejected::isDuplicateDeclaration()} means it was already declared.
     *
     * @throws OutcomeUnknown             a transport error or a 5xx may have reached URSSAF: never resent (ADR 0003)
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see ErrorKind}, {@see BusinessError}) (e.g. duplicate declaration)
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable, thrown only when the call provably did not reach URSSAF (429, token failure)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function declare(TenantId $tenantId, #[SensitiveParameter] Declaration $declaration): DeclarationResult;
}
