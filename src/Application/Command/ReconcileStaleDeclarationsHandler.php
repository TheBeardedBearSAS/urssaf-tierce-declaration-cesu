<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Reconciles the declarations (every tenant) left submitting for at least the policy "staleSubmissionAfter" (oldest
 * first): the process that sent them died or hung, so URSSAF may hold them. URSSAF offers no lookup (ADR 0003): each one
 * becomes outcome_unknown (stale submission), in a transaction of its own, for manual review
 * ({@see ItemOutcome::ManualReview}). Nothing is sent to URSSAF.
 */
final readonly class ReconcileStaleDeclarationsHandler
{
    public const STALE_SUBMISSION = 'stale_submission';

    public function __construct(
        private DeclarationRecordRepository $records,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    public function __invoke(ReconcileStaleDeclarations $command): BatchReport
    {
        $olderThan = Instant::fromDateTime($this->clock->now()->sub($this->policy->staleSubmissionAfter()));
        $items = [];
        foreach ($this->records->findStaleSubmissions($olderThan, $this->policy->pageSize($command->limit)) as $record) {
            $items[] = $this->reconcile($record);
        }

        return BatchReport::of(...$items);
    }

    private function reconcile(DeclarationRecord $record): BatchItem
    {
        $this->unitOfWork->run(function (RecordedEvents $events) use ($record): void {
            $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], Instant::fromDateTime($this->clock->now()));
            $this->records->save($record);
            $events->record(...$record->releaseEvents());
        });

        return new BatchItem($record->id()->value(), ItemOutcome::ManualReview, [self::STALE_SUBMISSION]);
    }
}
