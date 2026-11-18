<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Transaction;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TransactionManager;

/**
 * Transaction port for stores without transactions: runs the operation immediately, without any rollback.
 */
final class ImmediateTransactionManager implements TransactionManager
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        return $operation();
    }
}
