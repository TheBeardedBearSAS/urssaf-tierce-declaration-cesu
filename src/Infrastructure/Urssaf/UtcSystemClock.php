<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf;

use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;

/**
 * Default clock of {@see UrssafGatewayFactory} when none is given: the current system time, in UTC.
 *
 * @internal
 */
final readonly class UtcSystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
