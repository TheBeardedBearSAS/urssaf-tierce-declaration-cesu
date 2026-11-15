<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;

/**
 * Read model of a contributions estimate (method 040), amounts in cents.
 */
final readonly class ContributionsView
{
    private function __construct(public int $employerContributionsCents, public int $employeeContributionsCents) {}

    public static function fromEstimate(ContributionsEstimate $estimate): self
    {
        return new self($estimate->employerContributions()->cents(), $estimate->employeeContributions()->cents());
    }
}
