<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\Specification\EstimateRules;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;

/**
 * Contribution estimate request (040 `InputEstimation`, api-reference §2.6). Which income goes with which employment
 * kind is only inferred from the contract: no local rule pairs them.
 */
final readonly class EstimateRequest
{
    private function __construct(
        private EmploymentKind $employmentKind,
        private ?SimulatedHourlyIncome $hourlyIncome,
        private ?SimulatedFosterCarerIncome $fosterCarerIncome,
    ) {}

    /**
     * @throws ValidationFailed when an intrinsic rule is violated
     */
    public static function of(
        EmploymentKind $employmentKind,
        ?SimulatedHourlyIncome $hourlyIncome,
        ?SimulatedFosterCarerIncome $fosterCarerIncome,
    ): self {
        $request = new self($employmentKind, $hourlyIncome, $fosterCarerIncome);
        EstimateRules::intrinsic()->validate($request)->throwIfInvalid();

        return $request;
    }

    public function employmentKind(): EmploymentKind
    {
        return $this->employmentKind;
    }

    public function hourlyIncome(): ?SimulatedHourlyIncome
    {
        return $this->hourlyIncome;
    }

    public function fosterCarerIncome(): ?SimulatedFosterCarerIncome
    {
        return $this->fosterCarerIncome;
    }
}
