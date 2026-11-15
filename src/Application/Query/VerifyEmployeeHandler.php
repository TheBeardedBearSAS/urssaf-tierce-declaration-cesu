<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployeeVerificationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;

/**
 * Method 021 as a query: every failure propagates (a read is safe to retry).
 */
final readonly class VerifyEmployeeHandler
{
    public function __construct(private EmployeeVerifier $verifier) {}

    /**
     * @throws ValidationFailed
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(VerifyEmployee $query): EmployeeVerificationView
    {
        $employee = EmployeeReferenceFactory::forLookup()->fromDraft($query->employee);

        return EmployeeVerificationView::fromResult($this->verifier->verifyEmployee($query->tenantId, $employee));
    }
}
