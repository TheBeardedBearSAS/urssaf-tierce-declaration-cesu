<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\FileDatabase;

/**
 * The outbox contract on a SQLite file (own connection, real file locking) instead of the in-memory database.
 */
final class DoctrineFileOutboxContractTest extends TestCase
{
    use OutboxContract;

    private ?FileDatabase $file = null;

    protected function createOutbox(): Outbox
    {
        $this->file = new FileDatabase();

        return new DoctrineOutbox($this->file->entityManager());
    }

    protected function tearDown(): void
    {
        $this->file?->delete();
    }
}
