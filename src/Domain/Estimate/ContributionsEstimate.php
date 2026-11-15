<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Result of a contribution estimate (040 `OutputEstimation`, api-reference §2.6).
 */
final readonly class ContributionsEstimate
{
    private function __construct(private Contributions $contributions) {}

    public static function of(Contributions $contributions): self
    {
        return new self($contributions);
    }

    public function contributions(): Contributions
    {
        return $this->contributions;
    }

    public function employerContributions(): Money
    {
        return $this->contributions->employerContributions();
    }

    public function employeeContributions(): Money
    {
        return $this->contributions->employeeContributions();
    }
}
