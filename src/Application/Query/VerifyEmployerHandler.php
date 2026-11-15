<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerStatusView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Method 020 as a query: every failure propagates (a read is safe to retry).
 */
final readonly class VerifyEmployerHandler
{
    public function __construct(private EmployerVerifier $verifier, private Clock $clock) {}

    /**
     * @throws ValidationFailed
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(VerifyEmployer $query): EmployerStatusView
    {
        $employee = $query->employee instanceof EmployeeReferenceDraft ? EmployeeReferenceFactory::forLookup()->fromDraft($query->employee) : null;
        $verification = $employee instanceof EmployeeReference
            ? EmployerVerification::forPair($query->employer, $employee)
            : EmployerVerification::employerOnly($query->employer);
        $status = $this->verifier->verifyEmployer($query->tenantId, $verification);

        return EmployerStatusView::fromStatus($status, $employee, Instant::fromDateTime($this->clock->now()));
    }
}
