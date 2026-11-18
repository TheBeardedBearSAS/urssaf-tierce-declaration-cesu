<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Output;

/**
 * The "Field / Value" tables printed by the commands, as a map.
 */
final class Fields
{
    private function __construct() {}

    /**
     * @return array<string, string>
     */
    public static function of(string $display): array
    {
        $fields = [];
        foreach (\array_slice(Output::rows($display), 1) as [$field, $value]) {
            $fields[$field] = $value;
        }

        return $fields;
    }
}
