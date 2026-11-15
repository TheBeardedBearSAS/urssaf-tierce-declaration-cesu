<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\DeclarationAlreadyRecorded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationContext;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use Throwable;

/**
 * Declares a salary (method 050) and returns what happened (ADR 0003, ADR 0006, ADR 0009).
 *
 * Local refusals (validation, idempotency conflict, mandate) come before any record or claim. Re-entry for the same
 * tenant and external reference, by state of the existing record:
 * - different content: {@see DeclarationAlreadyRecorded} (except a rejected record, which is superseded);
 * - pending: sent now (after the mandate guard); submitting: {@see ItemOutcome::NotClaimed}; declared, already
 *   declared, outcome unknown or invalid credentials: {@see ItemOutcome::Replayed}, nothing is sent;
 * - error: sent again when attempts remain (policy raised), else {@see ItemOutcome::Exhausted};
 * - rejected (URSSAF recorded nothing): deleted and recorded again.
 */
final readonly class DeclareSalaryHandler
{
    public function __construct(
        private DeclarationRecordRepository $records,
        private DeclarationSubmitter $submitter,
        private MandateGuard $guard,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
        private DeclarationFactory $factory = new DeclarationFactory(),
    ) {}

    /**
     * @throws Throwable {@see ValidationFailed}, {@see DeclarationAlreadyRecorded}, {@see MandateRequired} (nothing was
     *                   recorded nor sent), {@see RetryableUrssafFailure} (the record is pending again), or an unexpected
     *                   failure of the call, rethrown after its outcome was recorded
     */
    public function __invoke(DeclareSalary $command): DeclarationSubmission
    {
        $declaration = $this->factory->fromDraft($command->declaration, DeclarationContext::of(
            $this->policy->today($this->clock->now()),
            $this->policy->allowFosterCarerDeclarations(),
        ));
        $snapshot = DeclarationSnapshot::fromDeclaration($declaration);
        $existing = $this->records->findByExternalReference($command->tenantId, $command->externalReference);
        if (!$existing instanceof DeclarationRecord) {
            return $this->recordAndSubmit($command, $declaration, $snapshot, null);
        }

        return $this->reenter($command, $declaration, $snapshot, $existing);
    }

    /**
     * Re-entry for the same tenant and external reference (see the class documentation).
     */
    private function reenter(
        DeclareSalary $command,
        Declaration $declaration,
        DeclarationSnapshot $snapshot,
        DeclarationRecord $existing,
    ): DeclarationSubmission {
        if ($existing->state() !== DeclarationState::Rejected && !$existing->hasSameContentAs($snapshot)) {
            throw DeclarationAlreadyRecorded::withExternalReference($command->externalReference, $existing->id());
        }

        return match ($existing->state()) {
            DeclarationState::Pending => $this->send($command, $declaration, $snapshot, $existing->id()),
            DeclarationState::Submitting => $this->described($existing, ItemOutcome::NotClaimed),
            DeclarationState::Declared,
            DeclarationState::AlreadyDeclared,
            DeclarationState::OutcomeUnknown,
            DeclarationState::InvalidCredentials => $this->described($existing, ItemOutcome::Replayed),
            DeclarationState::Error => $existing->canRetry($this->policy->maxSubmissionAttempts())
                ? $this->requeueAndSend($command, $declaration, $snapshot, $existing)
                : $this->described($existing, ItemOutcome::Exhausted),
            DeclarationState::Rejected => $this->recordAndSubmit($command, $declaration, $snapshot, $existing),
        };
    }

    private function described(DeclarationRecord $record, ItemOutcome $outcome): DeclarationSubmission
    {
        return new DeclarationSubmission(DeclarationView::fromRecord($record), $outcome);
    }

    private function send(
        DeclareSalary $command,
        Declaration $declaration,
        DeclarationSnapshot $snapshot,
        DeclarationRecordId $id,
    ): DeclarationSubmission {
        $this->assertMandate($command, $snapshot);

        return $this->submitter->submit($id, $command->tenantId, $declaration);
    }

    private function requeueAndSend(
        DeclareSalary $command,
        Declaration $declaration,
        DeclarationSnapshot $snapshot,
        DeclarationRecord $record,
    ): DeclarationSubmission {
        $this->assertMandate($command, $snapshot);
        $this->unitOfWork->run(function (RecordedEvents $events) use ($record): void {
            $record->retry(Instant::fromDateTime($this->clock->now()), $this->policy->maxSubmissionAttempts());
            $this->records->save($record);
            $events->record(...$record->releaseEvents());
        });

        return $this->submitter->submit($record->id(), $command->tenantId, $declaration);
    }

    private function assertMandate(DeclareSalary $command, DeclarationSnapshot $snapshot): void
    {
        $this->guard->assertMandate(
            $command->tenantId,
            $snapshot->employerCesuNumber(),
            Operation::DeclareSalary,
            $command->skipMandateCheck,
        );
    }

    /**
     * Records the declaration (superseding a rejected record) once the mandate guard accepted it, then sends it.
     */
    private function recordAndSubmit(
        DeclareSalary $command,
        Declaration $declaration,
        DeclarationSnapshot $snapshot,
        ?DeclarationRecord $superseded,
    ): DeclarationSubmission {
        $this->assertMandate($command, $snapshot);
        $id = DeclarationRecordId::fromString($this->ids->generate());
        $this->unitOfWork->run(function (RecordedEvents $events) use ($command, $snapshot, $superseded, $id): void {
            if ($superseded instanceof DeclarationRecord) {
                $this->records->delete($superseded->id());
            }
            $record = DeclarationRecord::record(
                $id,
                $command->tenantId,
                $command->externalReference,
                $snapshot,
                Instant::fromDateTime($this->clock->now()),
            );
            $this->records->save($record);
            $events->record(...$record->releaseEvents());
        });

        return $this->submitter->submit($id, $command->tenantId, $declaration);
    }
}
