<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository;

use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * One GROUP BY query per table (indexes "tenant_id, state / stage, ..." of the three tables), after the reference
 * DoctrineWorkflowStatistics.
 */
final readonly class DoctrineWorkflowStatistics implements WorkflowStatistics
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Override]
    public function employerStages(): array
    {
        return $this->count(EmployerAccount::class, 'tracking.stage');
    }

    #[Override]
    public function mandateStates(): array
    {
        return $this->count(Mandate::class, 'state');
    }

    #[Override]
    public function declarationStates(): array
    {
        return $this->count(DeclarationRecord::class, 'status.state');
    }

    /**
     * @param class-string $entity
     *
     * @return list<StateCount>
     */
    private function count(string $entity, string $stateField): array
    {
        /** @var list<array{tenantId: TenantId, state: BackedEnum, total: int|string}> $rows */
        $rows = $this->entityManager
            ->createQuery(\sprintf('SELECT r.tenantId AS tenantId, r.%1$s AS state, COUNT(r.id) AS total FROM %2$s r GROUP BY r.tenantId, r.%1$s', $stateField, $entity))
            ->getResult();

        return array_map(
            static fn(array $row): StateCount => new StateCount($row['tenantId'], (string) $row['state']->value, (int) $row['total']),
            $rows,
        );
    }
}
