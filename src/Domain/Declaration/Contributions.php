<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Contribution totals (`Cotisations`, api-reference §2.6 and §2.7): employer and employee contributions.
 */
final readonly class Contributions
{
    private function __construct(private Money $employerContributions, private Money $employeeContributions) {}

    public static function of(Money $employerContributions, Money $employeeContributions): self
    {
        return new self($employerContributions, $employeeContributions);
    }

    /**
     * `cotisationsPatronales`.
     */
    public function employerContributions(): Money
    {
        return $this->employerContributions;
    }

    /**
     * `cotisationsSalariales`.
     */
    public function employeeContributions(): Money
    {
        return $this->employeeContributions;
    }

    public function equals(self $other): bool
    {
        return $this->employerContributions->equals($other->employerContributions)
            && $this->employeeContributions->equals($other->employeeContributions);
    }
}
