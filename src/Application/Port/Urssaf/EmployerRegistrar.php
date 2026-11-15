<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Method 010: registers an employer account (api-reference §2.1).
 */
interface EmployerRegistrar
{
    /**
     * Creates the account and returns its Cesu number. Never resent automatically (the e-mail address is unique, ADR 0003);
     * see {@see UrssafRequestRejected::isEmailAlreadyKnown()}.
     *
     * @throws OutcomeUnknown             a transport error or a 5xx may have reached URSSAF: never resent (ADR 0003)
     * @throws UrssafRequestRejected      HTTP 400, classified ({@see ErrorKind}, {@see BusinessError}) (e.g. e-mail already known)
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable, thrown only when the call provably did not reach URSSAF (429, token failure)
     * @throws RateLimitExceeded          the local limiter refused the call
     *
     * Any other {@see UrssafException} means URSSAF answered something the contract does not describe.
     */
    public function registerEmployer(TenantId $tenantId, #[SensitiveParameter] NewEmployer $employer): CesuNumber;
}
