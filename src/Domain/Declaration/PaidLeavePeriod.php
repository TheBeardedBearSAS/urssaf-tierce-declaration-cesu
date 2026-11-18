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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;

/**
 * Paid leave period (`PeriodeCongePaye`, api-reference §2.7): start, end (not before the start) and number of days
 * (0 to 31, multiple of 0.5). Every field is required: URSSAF refuses incomplete periods.
 */
final readonly class PaidLeavePeriod
{
    public const MAX_HALF_DAYS = 62;
    private const SUBJECT = 'paid_leave_period';

    private function __construct(
        private CalendarDate $start,
        private CalendarDate $end,
        private HalfDays $days,
    ) {}

    /**
     * @throws InvalidValue "paid_leave_period.end_before_start" or "paid_leave_days.out_of_range"
     */
    public static function of(CalendarDate $start, CalendarDate $end, HalfDays $days): self
    {
        if ($end->isBefore($start)) {
            throw InvalidValue::because(
                self::SUBJECT . '.end_before_start',
                'Invalid paid_leave_period: the end must not be before the start.',
            );
        }

        return new self($start, $end, $days->assertAtMost(HalfDays::fromHalfDays(self::MAX_HALF_DAYS), 'paid_leave_days'));
    }

    public function start(): CalendarDate
    {
        return $this->start;
    }

    public function end(): CalendarDate
    {
        return $this->end;
    }

    public function days(): HalfDays
    {
        return $this->days;
    }

    /**
     * Bounds included: periods that share a day overlap.
     */
    public function overlaps(self $other): bool
    {
        return !$this->start->isAfter($other->end) && !$other->start->isAfter($this->end);
    }

    public function isWithin(EmploymentPeriod $period): bool
    {
        return $period->contains($this->start) && $period->contains($this->end);
    }

    public function equals(self $other): bool
    {
        return $this->start->equals($other->start)
            && $this->end->equals($other->end)
            && $this->days->equals($other->days);
    }
}
