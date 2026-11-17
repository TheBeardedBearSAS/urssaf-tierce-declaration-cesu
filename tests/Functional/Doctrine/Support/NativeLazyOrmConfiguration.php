<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support;

/**
 * Stands for an ORM configuration that implements the native lazy objects.
 */
final class NativeLazyOrmConfiguration
{
    public function enableNativeLazyObjects(bool $enabled): bool
    {
        return $enabled;
    }
}
