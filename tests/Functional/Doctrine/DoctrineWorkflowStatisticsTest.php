<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\WorkflowStatisticsContract;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * Doctrine statistics against the shared scenarios; the aggregates are stored through the Doctrine repositories.
 */
final class DoctrineWorkflowStatisticsTest extends TestCase
{
    use WorkflowStatisticsContract;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
    }

    protected function createStatistics(): WorkflowStatistics
    {
        return new DoctrineWorkflowStatistics($this->entityManager);
    }

    protected function store(object ...$aggregates): void
    {
        $accounts = new DoctrineEmployerAccountRepository($this->entityManager);
        $mandates = new DoctrineMandateRepository($this->entityManager);
        $declarations = new DoctrineDeclarationRecordRepository($this->entityManager);
        foreach ($aggregates as $aggregate) {
            match (true) {
                $aggregate instanceof EmployerAccount => $accounts->save($aggregate),
                $aggregate instanceof Mandate => $mandates->save($aggregate),
                $aggregate instanceof DeclarationRecord => $declarations->save($aggregate),
                default => self::fail('Unsupported aggregate ' . $aggregate::class . '.'),
            };
        }
    }
}
