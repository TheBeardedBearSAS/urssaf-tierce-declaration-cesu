<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory;

use Override;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Counts the records of the in-memory repositories.
 */
final readonly class InMemoryWorkflowStatistics implements WorkflowStatistics
{
    public function __construct(
        private InMemoryEmployerAccountRepository $accounts,
        private InMemoryMandateRepository $mandates,
        private InMemoryDeclarationRecordRepository $declarations,
    ) {}

    #[Override]
    public function employerStages(): array
    {
        return self::count(array_map(
            static fn(EmployerAccount $account): array => [$account->tenantId(), $account->stage()->value],
            $this->accounts->all(),
        ));
    }

    #[Override]
    public function mandateStates(): array
    {
        return self::count(array_map(
            static fn(Mandate $mandate): array => [$mandate->tenantId(), $mandate->state()->value],
            $this->mandates->all(),
        ));
    }

    #[Override]
    public function declarationStates(): array
    {
        return self::count(array_map(
            static fn(DeclarationRecord $record): array => [$record->tenantId(), $record->state()->value],
            $this->declarations->all(),
        ));
    }

    /**
     * @param list<array{TenantId, string}> $records tenant and state of each record
     *
     * @return list<StateCount>
     */
    private static function count(array $records): array
    {
        $tenants = [];
        $counts = [];
        foreach ($records as [$tenantId, $state]) {
            $tenants[$tenantId->value()] = $tenantId;
            $counts[$tenantId->value()][$state] = ($counts[$tenantId->value()][$state] ?? 0) + 1;
        }
        $stateCounts = [];
        foreach ($counts as $tenant => $byState) {
            foreach ($byState as $state => $count) {
                $stateCounts[] = new StateCount($tenants[$tenant], $state, $count);
            }
        }

        return $stateCounts;
    }
}
