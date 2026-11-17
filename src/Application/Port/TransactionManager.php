<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port;

use Throwable;

interface TransactionManager
{
    /**
     * Runs the operation in a transaction: committed when it returns, rolled back (and the throwable rethrown
     * unchanged) when it throws.
     *
     * @template T
     *
     * @param callable(): T $operation
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     *
     * @throws Throwable
     */
    public function transactional(callable $operation): mixed;
}
