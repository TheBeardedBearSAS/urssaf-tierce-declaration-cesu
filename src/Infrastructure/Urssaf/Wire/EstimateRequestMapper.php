<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedFosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedHourlyIncome;

/**
 * Method 040 (estimate contributions): builds the `InputEstimation` body (api-reference §2.6). The path is
 * {@see ApiPaths::estimate()}.
 */
final readonly class EstimateRequestMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toBody(EstimateRequest $request): array
    {
        $fosterCarerIncome = $request->fosterCarerIncome();
        $hourlyIncome = $request->hourlyIncome();

        return array_filter(
            [
                'typeEmploi' => $request->employmentKind()->value,
                'revenusAf' => $fosterCarerIncome instanceof SimulatedFosterCarerIncome ? $this->fosterCarer($fosterCarerIncome) : null,
                'revenusEf' => $hourlyIncome instanceof SimulatedHourlyIncome ? $this->hourly($hourlyIncome) : null,
            ],
            static fn(mixed $value): bool => $value !== null,
        );
    }

    /**
     * `RevenusAfSimu`: the foster carer income and the geographic zone (last key).
     *
     * @return array<string, mixed>
     */
    private function fosterCarer(SimulatedFosterCarerIncome $simulated): array
    {
        return IncomeEncoding::fosterCarer($simulated->income()) + [
            'codeZoneGeographique' => $simulated->geographicZone()->value,
        ];
    }

    /**
     * `RevenusEfSimu`.
     *
     * @return array<string, mixed>
     */
    private function hourly(SimulatedHourlyIncome $simulated): array
    {
        return IncomeEncoding::hourly(
            $simulated->hourlyWage(),
            $simulated->hours(),
            $simulated->paidLeaveHours(),
            $simulated->supplements(),
        ) + [
            'typeSalaire' => $simulated->salaryType()->value,
            'codeExoneration' => $simulated->exemptionCode()->value,
            'codeZoneGeographique' => $simulated->geographicZone()->value,
            'congesPayes' => $simulated->paidLeaveIncluded(),
        ];
    }
}
