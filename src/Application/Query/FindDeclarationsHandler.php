<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

/**
 * Returns the views in the order of the query (external references first, then ids); unknown records and records of
 * another tenant are left out. A state listing returns the least recently updated first.
 */
final readonly class FindDeclarationsHandler
{
    public function __construct(private DeclarationRecordRepository $records, private WorkflowPolicy $policy) {}

    /**
     * @return list<DeclarationView>
     */
    public function __invoke(FindDeclarations $query): array
    {
        $found = $query->state instanceof DeclarationState
            ? $this->records->findByState($query->tenantId, $query->state, $this->policy->pageSize($query->limit))
            : [
                ...array_map(
                    fn(ExternalReference $reference): ?DeclarationRecord => $this->records->findByExternalReference($query->tenantId, $reference),
                    $query->externalReferences,
                ),
                ...array_map($this->find(...), $query->recordIds),
            ];
        $views = [];
        foreach ($found as $record) {
            if (!$record instanceof DeclarationRecord || !$record->tenantId()->equals($query->tenantId)) {
                continue;
            }
            $views[] = DeclarationView::fromRecord($record);
        }

        return $views;
    }

    private function find(DeclarationRecordId $id): ?DeclarationRecord
    {
        try {
            return $this->records->get($id);
        } catch (RecordNotFound) {
            return null;
        }
    }
}
