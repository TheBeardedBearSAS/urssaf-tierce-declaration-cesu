<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ReconcileStaleDeclarations;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service\DeclarationPolicies;

final class ReconcileStaleDeclarationsHandlerTest extends TestCase
{
    public function testAStaleSubmissionBecomesAnOutcomeUnknownForManualReview(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Submitting);

        $report = $this->reconcile($harness, null);

        static::assertSame(1, $report->total());
        static::assertSame('record-1', $report->items()[0]->subject);
        static::assertSame(ItemOutcome::ManualReview, $report->items()[0]->outcome);
        static::assertSame(['stale_submission'], $report->items()[0]->codes);
        $record = $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'));
        static::assertSame(DeclarationState::OutcomeUnknown, $record->state());
        static::assertSame(OutcomeUnknownReason::StaleSubmission, $record->outcomeUnknownReason());
        static::assertSame([], $record->errors());
        static::assertSame('2026-11-15T10:00:00Z', $record->updatedAt()->toIso8601());
        static::assertSame(
            ['begin', 'save:record-1', 'commit', 'publish:DeclarationOutcomeUnknown'],
            $harness->journal->entries(),
        );
        static::assertSame([], $harness->gateway->calls());
    }

    public function testEachRecordHasATransactionOfItsOwnEveryTenantIsCovered(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Submitting);
        $harness->record(2, DeclarationState::Submitting, 1, Harness::OTHER_TENANT);

        $report = $this->reconcile($harness, null);

        static::assertSame(2, $report->count(ItemOutcome::ManualReview));
        static::assertSame(
            [
                'begin',
                'save:record-1',
                'commit',
                'publish:DeclarationOutcomeUnknown',
                'begin',
                'save:record-2',
                'commit',
                'publish:DeclarationOutcomeUnknown',
            ],
            $harness->journal->entries(),
        );
    }

    public function testTheStaleDelayBoundaryIsInclusive(): void
    {
        $atBoundary = new Harness(DeclarationPolicies::staleAfter('PT1H'));
        $atBoundary->record(1, DeclarationState::Submitting);
        $justBefore = new Harness(DeclarationPolicies::staleAfter('PT1H1S'));
        $justBefore->record(1, DeclarationState::Submitting);

        static::assertSame(1, $this->reconcile($atBoundary, null)->total());
        static::assertSame(0, $this->reconcile($justBefore, null)->total());
        static::assertSame([], $justBefore->journal->entries());
    }

    public function testOtherStatesAreIgnored(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->record(2, DeclarationState::OutcomeUnknown);
        $harness->record(3, DeclarationState::Declared);

        static::assertSame(0, $this->reconcile($harness, null)->total());
        static::assertSame([], $harness->journal->entries());
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $harness = new Harness(DeclarationPolicies::pageSizes(1, 2));
        $harness->record(1, DeclarationState::Submitting);
        $harness->record(2, DeclarationState::Submitting);
        $harness->record(3, DeclarationState::Submitting);

        static::assertSame(1, $this->reconcile($harness, null)->total());
        static::assertSame(2, $this->reconcile($harness, 5)->total());
    }

    private function reconcile(Harness $harness, ?int $limit): BatchReport
    {
        return (new ReconcileStaleDeclarationsHandler($harness->declarations, $harness->clock, $harness->unitOfWork, $harness->policy))(
            new ReconcileStaleDeclarations($limit),
        );
    }
}
