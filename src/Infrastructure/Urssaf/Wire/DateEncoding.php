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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Dates of the URSSAF wire contract are date-only `YYYY-MM-DD` strings in both directions (ADR 0001): never a time
 * part, `Z` or offset.
 */
final class DateEncoding
{
    public static function encode(CalendarDate $date): string
    {
        return $date->toString();
    }

    /**
     * @throws UnexpectedUrssafResponse when the value is not a strict `Y-m-d` calendar date
     */
    public static function decode(string $value): CalendarDate
    {
        try {
            return CalendarDate::fromString($value);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('date is not a Y-m-d calendar date', $exception);
        }
    }
}
