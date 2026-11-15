<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Applies the operator's finding to a record in state outcome_unknown (ADR 0003). Nothing is sent to URSSAF.
 */
final readonly class ResolveDeclarationOutcomeHandler
{
    public function __construct(
        private DeclarationLocator $locator,
        private DeclarationRecordRepository $records,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws RecordNotFound         "declaration_record.not_found"
     * @throws InvalidStateTransition the record is not in state outcome_unknown
     * @throws InvariantViolation     "declaration_record.duplicate_declaration_id" (repository)
     */
    public function __invoke(ResolveDeclarationOutcome $command): DeclarationView
    {
        $record = $this->locator->locate($command->tenantId, $command->declaration);
        $this->unitOfWork->run(function (RecordedEvents $events) use ($command, $record): void {
            $this->resolve($record, $command->declarationId, Instant::fromDateTime($this->clock->now()));
            $this->records->save($record);
            $events->record(...$record->releaseEvents());
        });

        return DeclarationView::fromRecord($record);
    }

    /**
     * @throws InvalidStateTransition
     */
    private function resolve(DeclarationRecord $record, ?DeclarationId $declarationId, Instant $now): void
    {
        if (!$declarationId instanceof DeclarationId) {
            $record->resolveAsNotReceived($now);

            return;
        }
        $record->resolveAsDeclared($declarationId, $now);
    }
}
