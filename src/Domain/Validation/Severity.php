<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation;

enum Severity: string
{
    /** Blocks the operation: URSSAF would reject the request. */
    case Error = 'error';

    /** Informative only: the operation may proceed. */
    case Warning = 'warning';
}
