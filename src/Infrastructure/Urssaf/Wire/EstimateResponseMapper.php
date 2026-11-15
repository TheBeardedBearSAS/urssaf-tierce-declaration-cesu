<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the 040 response `OutputEstimation` (api-reference §2.6): the required `cotisations` object.
 */
final readonly class EstimateResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $payload): ContributionsEstimate
    {
        return ContributionsEstimate::of((new ContributionsMapper())->map($payload->requireObject('cotisations')));
    }
}
