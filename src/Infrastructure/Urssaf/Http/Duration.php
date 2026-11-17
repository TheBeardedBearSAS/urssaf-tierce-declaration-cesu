<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http;

use DateTimeImmutable;

/**
 * Elapsed time helper built on the {@see \TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock} port values.
 *
 * @internal
 */
final class Duration
{
    public static function millisecondsSince(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $microseconds = self::epochMicroseconds($end) - self::epochMicroseconds($start);

        return intdiv($microseconds, 1000);
    }

    private static function epochMicroseconds(DateTimeImmutable $instant): int
    {
        return (int) $instant->format('Uu');
    }
}
