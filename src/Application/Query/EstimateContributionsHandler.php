<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ContributionsView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ContributionEstimator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;

final readonly class EstimateContributionsHandler
{
    public function __construct(private ContributionEstimator $estimator) {}

    /**
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(EstimateContributions $query): ContributionsView
    {
        return ContributionsView::fromEstimate($this->estimator->estimate($query->tenantId, $query->request));
    }
}
