<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\FileDatabase;

/**
 * The mandate contract on a SQLite file (own connection, real file locking) instead of the in-memory database.
 */
final class DoctrineFileMandateRepositoryContractTest extends TestCase
{
    use MandateRepositoryContract {
        setUp as private contractSetUp;
    }

    private FileDatabase $file;

    protected function setUp(): void
    {
        $this->file = new FileDatabase();
        $this->contractSetUp();
    }

    protected function createRepository(): MandateRepository
    {

        return new DoctrineMandateRepository($this->file->entityManager());
    }

    protected function tearDown(): void
    {
        $this->file->delete();
    }
}
