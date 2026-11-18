<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\HourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Hourly income of an estimate (`RevenusEfSimu`, api-reference §2.6). Unlike the declaration there is no 10 % rule
 * between the paid leave flag and the paid leave hours: the official example sends `congesPayes: true` with
 * `heuresCp: 0`. The public constructor is the factory (more than 5 parts).
 */
final readonly class SimulatedHourlyIncome
{
    /**
     * @param int|null               $paidLeaveHours omitted is 0 server-side
     * @param list<SalarySupplement> $supplements
     *
     * @throws InvalidValue "hourly_wage.out_of_range" or "paid_leave_hours.out_of_range"
     */
    public function __construct(
        private Money $hourlyWage,
        private WorkedHours $hours,
        private ?int $paidLeaveHours,
        private array $supplements,
        private SalaryType $salaryType,
        private ExemptionCode $exemptionCode,
        private GeographicZone $geographicZone,
        private bool $paidLeaveIncluded,
    ) {
        $hourlyWage->assertAtMost(Money::fromCents(HourlyIncome::MAX_HOURLY_WAGE_CENTS), 'hourly_wage');
        if ($paidLeaveHours !== null && ($paidLeaveHours < 0 || $paidLeaveHours > HourlyIncome::MAX_PAID_LEAVE_HOURS)) {
            throw InvalidValue::outOfRange('paid_leave_hours', 0, HourlyIncome::MAX_PAID_LEAVE_HOURS);
        }
    }

    public function hourlyWage(): Money
    {
        return $this->hourlyWage;
    }

    public function hours(): WorkedHours
    {
        return $this->hours;
    }

    public function paidLeaveHours(): ?int
    {
        return $this->paidLeaveHours;
    }

    /**
     * @return list<SalarySupplement>
     */
    public function supplements(): array
    {
        return $this->supplements;
    }

    public function salaryType(): SalaryType
    {
        return $this->salaryType;
    }

    public function exemptionCode(): ExemptionCode
    {
        return $this->exemptionCode;
    }

    public function geographicZone(): GeographicZone
    {
        return $this->geographicZone;
    }

    /**
     * `congesPayes`: true when the 10 % paid leave is included in the hourly wage.
     */
    public function paidLeaveIncluded(): bool
    {
        return $this->paidLeaveIncluded;
    }
}
