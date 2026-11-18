<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Hours worked in the month (`heuresTravaillees`, `nbHeureSupplementaire1`, `nbHeureSupplementaire2`, api-reference
 * §2.6 and §2.7). The 40 h / 8 h overtime thresholds are a specification, see {@see Specification\WorkedHoursRules}.
 */
final readonly class WorkedHours
{
    public const MAX_WORKED_HOURS = 200;
    public const MAX_FIRST_TIER_OVERTIME_HOURS = 35;
    public const MAX_SECOND_TIER_OVERTIME_HOURS = 40;

    private function __construct(
        private int $workedHours,
        private ?int $firstTierOvertimeHours,
        private ?int $secondTierOvertimeHours,
    ) {}

    /**
     * @param int|null $firstTierOvertimeHours  overtime paid +25 %
     * @param int|null $secondTierOvertimeHours overtime paid +50 %
     *
     * @throws InvalidValue "worked_hours.out_of_range", "first_tier_overtime_hours.out_of_range" or
     *                      "second_tier_overtime_hours.out_of_range"
     */
    public static function of(int $workedHours, ?int $firstTierOvertimeHours, ?int $secondTierOvertimeHours): self
    {
        self::assertRange($workedHours, 'worked_hours', self::MAX_WORKED_HOURS);
        if ($firstTierOvertimeHours !== null) {
            self::assertRange($firstTierOvertimeHours, 'first_tier_overtime_hours', self::MAX_FIRST_TIER_OVERTIME_HOURS);
        }
        if ($secondTierOvertimeHours !== null) {
            self::assertRange($secondTierOvertimeHours, 'second_tier_overtime_hours', self::MAX_SECOND_TIER_OVERTIME_HOURS);
        }

        return new self($workedHours, $firstTierOvertimeHours, $secondTierOvertimeHours);
    }

    public function workedHours(): int
    {
        return $this->workedHours;
    }

    public function firstTierOvertimeHours(): ?int
    {
        return $this->firstTierOvertimeHours;
    }

    public function secondTierOvertimeHours(): ?int
    {
        return $this->secondTierOvertimeHours;
    }

    /**
     * The +25 % overtime hours, 0 when none was given.
     */
    public function firstTierOvertimeHoursOrZero(): int
    {
        return $this->firstTierOvertimeHours ?? 0;
    }

    /**
     * The +50 % overtime hours, 0 when none was given.
     */
    public function secondTierOvertimeHoursOrZero(): int
    {
        return $this->secondTierOvertimeHours ?? 0;
    }

    public function equals(self $other): bool
    {
        return $this->workedHours === $other->workedHours
            && $this->firstTierOvertimeHours === $other->firstTierOvertimeHours
            && $this->secondTierOvertimeHours === $other->secondTierOvertimeHours;
    }

    private static function assertRange(int $value, string $subject, int $max): void
    {
        if ($value < 0 || $value > $max) {
            throw InvalidValue::outOfRange($subject, 0, $max);
        }
    }
}
