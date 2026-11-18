<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status;

/**
 * "etatMandatSepa" of method 020 (api-reference §2.2, §3.4).
 */
enum SepaMandateState: string
{
    case Absent = 'absent';
    case Inactive = 'inactif';
    case Active = 'actif';

    /**
     * Null for an unknown value (the raw string is kept by {@see EmployerStatus}).
     */
    public static function tryFromWire(string $raw): ?self
    {
        return self::tryFrom(trim($raw));
    }
}
