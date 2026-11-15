<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\FileDatabase;

/**
 * The employer account contract on a SQLite file (own connection, real file locking) instead of the in-memory database.
 */
final class DoctrineFileEmployerAccountRepositoryContractTest extends TestCase
{
    use EmployerAccountRepositoryContract {
        setUp as private contractSetUp;
    }

    private FileDatabase $file;

    protected function setUp(): void
    {
        $this->file = new FileDatabase();
        $this->contractSetUp();
    }

    protected function createRepository(): EmployerAccountRepository
    {

        return new DoctrineEmployerAccountRepository($this->file->entityManager());
    }

    protected function tearDown(): void
    {
        $this->file->delete();
    }
}
