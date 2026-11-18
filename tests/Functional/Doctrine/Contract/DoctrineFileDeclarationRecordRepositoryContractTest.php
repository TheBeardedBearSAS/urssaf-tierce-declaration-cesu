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

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\FileDatabase;

/**
 * The declaration record contract on a SQLite file (own connection, real file locking) instead of the in-memory database.
 */
final class DoctrineFileDeclarationRecordRepositoryContractTest extends TestCase
{
    use DeclarationRecordRepositoryContract {
        setUp as private contractSetUp;
    }

    private FileDatabase $file;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->file = new FileDatabase();
        $this->entityManager = $this->file->entityManager();
        $this->contractSetUp();
    }

    protected function createRepository(): DeclarationRecordRepository
    {
        return new DoctrineDeclarationRecordRepository($this->entityManager);
    }

    protected function transactions(): TransactionManager
    {
        return new DoctrineTransactionManager($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->file->delete();
    }
}
