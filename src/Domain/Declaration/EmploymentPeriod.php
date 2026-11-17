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

/**
 * Employment period of a declaration (`dateDebut` / `dateFin`, api-reference §2.7): the end is not before the start and
 * both fall in the same month. "Start not after today" is a contextual rule on the start date, see
 * {@see Specification\EmploymentNotStartedAfterToday}.
 */
final readonly class EmploymentPeriod
{
    private const SUBJECT = 'employment_period';

    private function __construct(private CalendarDate $start, private CalendarDate $end) {}

    /**
     * A one-day period (start = end) is valid.
     *
     * @throws InvalidValue "employment_period.end_before_start" or "employment_period.not_single_month"
     */
    public static function between(CalendarDate $start, CalendarDate $end): self
    {
        if ($end->isBefore($start)) {
            throw InvalidValue::because(
                self::SUBJECT . '.end_before_start',
                'Invalid employment_period: the end must not be before the start.',
            );
        }
        if (!$start->isSameMonthAs($end)) {
            throw InvalidValue::because(
                self::SUBJECT . '.not_single_month',
                'Invalid employment_period: the period must not span several months.',
            );
        }

        return new self($start, $end);
    }

    public function start(): CalendarDate
    {
        return $this->start;
    }

    public function end(): CalendarDate
    {
        return $this->end;
    }

    /**
     * Both bounds included.
     */
    public function contains(CalendarDate $date): bool
    {
        return !$date->isBefore($this->start) && !$date->isAfter($this->end);
    }

    public function startsAfter(CalendarDate $date): bool
    {
        return $this->start->isAfter($date);
    }

    public function equals(self $other): bool
    {
        return $this->start->equals($other->start) && $this->end->equals($other->end);
    }
}
