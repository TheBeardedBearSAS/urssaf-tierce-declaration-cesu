<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;

/**
 * Foster carer income of an estimate (`RevenusAfSimu`, api-reference §2.6): the declaration income plus the
 * geographic zone.
 */
final readonly class SimulatedFosterCarerIncome
{
    private function __construct(private FosterCarerIncome $income, private GeographicZone $geographicZone) {}

    public static function of(FosterCarerIncome $income, GeographicZone $geographicZone): self
    {
        return new self($income, $geographicZone);
    }

    public function income(): FosterCarerIncome
    {
        return $this->income;
    }

    public function geographicZone(): GeographicZone
    {
        return $this->geographicZone;
    }
}
