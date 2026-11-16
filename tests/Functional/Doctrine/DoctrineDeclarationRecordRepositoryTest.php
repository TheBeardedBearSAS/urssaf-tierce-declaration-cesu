<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\DeclarationRecordRepositoryContract;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\BeforeNextFlush;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\FileDatabase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * Contract and Doctrine-specific scenarios of the declaration record repository: one method per scenario.
 * @mago-expect lint:too-many-methods
 */
final class DoctrineDeclarationRecordRepositoryTest extends TestCase
{
    use DeclarationRecordRepositoryContract {
        setUp as private contractSetUp;
    }

    private EntityManagerInterface $entityManager;

    private ?FileDatabase $file = null;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->contractSetUp();
    }

    protected function createRepository(): DeclarationRecordRepository
    {
        return new DoctrineDeclarationRecordRepository($this->entityManager);
    }

    protected function transactions(): TransactionManager
    {
        return new DoctrineTransactionManager($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->file?->delete();
    }

    public function testItReturnsTheManagedInstanceUntilTheEntityManagerIsCleared(): void
    {
        $record = ContractSamples::pendingRecord(1);
        $this->repository->save($record);
        $id = DeclarationRecordId::fromString('record-1');

        static::assertSame($record, $this->repository->get($id));

        $this->entityManager->clear();
        $reloaded = $this->repository->get($id);

        static::assertNotSame($record, $reloaded);
        static::assertSame([], $reloaded->releaseEvents());
        $record->releaseEvents();
        static::assertEquals($record, $reloaded);
    }

    public function testAnUnmanagedRecordReplacesTheStoredRowOfTheSameIdentity(): void
    {
        $this->repository->save(ContractSamples::pendingRecord(1));
        $this->entityManager->clear();

        $this->repository->save(ContractSamples::record(1, DeclarationState::Submitting));
        $this->entityManager->clear();

        static::assertSame(DeclarationState::Submitting, $this->repository->get(DeclarationRecordId::fromString('record-1'))->state());
        static::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM urssaf_cesu_declaration_records'));
    }

    public function testAClaimDropsTheStaleManagedCopySoThatTheNextReadSeesTheClaimedRowAndBumpsTheVersion(): void
    {
        $record = ContractSamples::pendingRecord(1);
        $this->repository->save($record);

        static::assertTrue($this->repository->claimForSubmission($record->id(), ContractSamples::at('2026-11-10T10:00:00Z')));

        static::assertFalse($this->entityManager->contains($record));
        $claimed = $this->repository->get($record->id());
        static::assertNotSame($record, $claimed);
        static::assertSame(DeclarationState::Submitting, $claimed->state());
        static::assertSame(1, $claimed->attempts());
        static::assertSame('2026-11-10T10:00:00Z', $claimed->lastAttemptAt()?->toIso8601());
        static::assertSame('2026-11-10T10:00:00Z', $claimed->updatedAt()->toIso8601());
        static::assertSame(2, $this->entityManager->getConnection()->fetchOne('SELECT version FROM urssaf_cesu_declaration_records'));
    }

    public function testAFailedClaimKeepsTheManagedCopyAndTheVersion(): void
    {
        $record = ContractSamples::record(1, DeclarationState::Submitting);
        $this->repository->save($record);

        static::assertFalse($this->repository->claimForSubmission($record->id(), ContractSamples::at('2026-11-10T10:00:00Z')));

        static::assertTrue($this->entityManager->contains($record));
        static::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT version FROM urssaf_cesu_declaration_records'));
    }

    public function testAClaimKeepsTheErrorsTheResultAndClearsTheOutcomeUnknownReason(): void
    {
        $record = ContractSamples::pendingRecord(1);
        $record->startSubmission(ContractSamples::at('2026-11-10T10:00:00Z'));
        $record->markFailed(ContractSamples::errors(), ContractSamples::at('2026-11-10T10:00:00Z'));
        $record->retry(ContractSamples::at('2026-11-10T11:00:00Z'));
        $this->repository->save($record);
        $this->entityManager->getConnection()->executeStatement("UPDATE urssaf_cesu_declaration_records SET outcome_unknown_reason = 'stale_submission'");
        $this->entityManager->clear();

        static::assertTrue($this->repository->claimForSubmission($record->id(), ContractSamples::at('2026-11-10T12:00:00Z')));

        $claimed = $this->repository->get($record->id());
        static::assertNull($claimed->outcomeUnknownReason());
        static::assertSame(2, $claimed->attempts());
        static::assertSame(ContractSamples::errors()[0]->code(), $claimed->errors()[0]->code());
    }

    public function testARefusedDuplicateDoesNotCloseTheEntityManager(): void
    {
        $this->repository->save(ContractSamples::pendingRecord(1));

        try {
            $this->repository->save(ContractSamples::pendingRecordOfReference(1, 2));
            static::fail('Expected a violation.');
        } catch (InvariantViolation) {
            static::assertTrue($this->entityManager->isOpen());
        }
    }

    public function testEqualUpdateInstantsAreOrderedByCreationThenIdentifierWhateverTheInsertionOrder(): void
    {
        // Insertion order, creation order and identifier order all differ; every record is updated at the same instant.
        foreach ([[2, '2026-11-03T00:00:00Z'], [3, '2026-11-01T00:00:00Z'], [1, '2026-11-03T00:00:00Z']] as [$number, $createdAt]) {
            $record = ContractSamples::pendingRecord($number, ContractSamples::TENANT, $createdAt);
            $record->startSubmission(ContractSamples::at('2026-11-04T00:00:00Z'));
            $this->repository->save($record);
        }
        $tenant = ContractSamples::tenant();

        static::assertSame(
            ['record-3', 'record-1', 'record-2'],
            self::recordIds($this->repository->findByState($tenant, DeclarationState::Submitting, 10)),
        );
        static::assertSame(
            ['record-3', 'record-1', 'record-2'],
            self::recordIds($this->repository->findStaleSubmissions(ContractSamples::at('2026-11-04T00:00:00Z'), 10)),
        );
    }

    public function testTwoWorkersOnTheSameDatabaseCannotBothClaim(): void
    {
        $this->file = new FileDatabase();
        $first = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        $second = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        $first->save(ContractSamples::pendingRecord(1));
        $id = DeclarationRecordId::fromString('record-1');
        $staleForSecond = $second->get($id);

        $firstWon = $first->claimForSubmission($id, ContractSamples::at('2026-11-10T10:00:00Z'));
        $secondWon = $second->claimForSubmission($id, ContractSamples::at('2026-11-10T10:00:01Z'));

        static::assertTrue($firstWon);
        static::assertFalse($secondWon);
        static::assertSame(DeclarationState::Pending, $staleForSecond->state());
        $stored = (new DoctrineDeclarationRecordRepository($this->file->entityManager()))->get($id);
        static::assertSame(DeclarationState::Submitting, $stored->state());
        static::assertSame('2026-11-10T10:00:00Z', $stored->lastAttemptAt()?->toIso8601());
    }

    public function testAConcurrentDuplicateExternalReferenceIsReportedAsAViolation(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineDeclarationRecordRepository($entityManager);
        $rival = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::pendingRecordOfReference(1, 2));
        });

        try {
            $repository->save(ContractSamples::pendingRecord(1));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.duplicate_external_reference', $exception->violationCode());
        }
    }

    public function testAConcurrentDuplicateDeclarationIdIsReportedAsAViolation(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineDeclarationRecordRepository($entityManager);
        $rival = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            // Another external reference ("declaration-5"), but the same declaration id as the record saved below.
            $rival->save(ContractSamples::recordOfReference(5, 2, DeclarationState::Declared));
        });
        $record = ContractSamples::pendingRecord(1);
        $record->startSubmission(ContractSamples::at());
        $record->markDeclared(ContractSamples::result(5), ContractSamples::at());

        try {
            $repository->save($record);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.duplicate_declaration_id', $exception->violationCode());
        }
    }

    public function testAnUnexplainedConstraintViolationIsRethrownUnchanged(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineDeclarationRecordRepository($entityManager);
        $rival = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        // Same identifier, different tenant and reference: only the primary key collides.
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::pendingRecordOfReference(2, 1, ContractSamples::OTHER_TENANT));
        });

        $this->expectException(UniqueConstraintViolationException::class);
        $repository->save(ContractSamples::pendingRecord(1));
    }

    public function testASupersedeInsideATransactionThatThenFailsRollsBackTheDeleteAndTheInsert(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Rejected));
        $transactions = $this->transactions();

        try {
            $transactions->transactional(function (): never {
                $this->repository->delete(DeclarationRecordId::fromString('record-1'));
                $this->repository->save(ContractSamples::pendingRecordOfReference(1, 2));

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertSame(['record-1'], $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM urssaf_cesu_declaration_records'));
        static::assertSame(DeclarationState::Rejected, $this->repository->get(DeclarationRecordId::fromString('record-1'))->state());
    }

    public function testADeleteByEmployerInsideATransactionThatThenFailsKeepsTheRecords(): void
    {
        $this->repository->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 2, DeclarationState::Pending));

        try {
            $this->transactions()->transactional(function (): never {
                $this->repository->deleteByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1), [DeclarationState::Declared, DeclarationState::Pending]);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertEqualsCanonicalizing(['record-1', 'record-2'], $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM urssaf_cesu_declaration_records'));
        static::assertSame(DeclarationState::Declared, $this->repository->get(DeclarationRecordId::fromString('record-1'))->state());
    }

    public function testASaveOfACopyReadBeforeAnotherProcessClaimedTheRecordIsRefused(): void
    {
        $this->file = new FileDatabase();
        $first = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        $second = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        $first->save(ContractSamples::pendingRecord(1));
        $id = DeclarationRecordId::fromString('record-1');
        $stale = $second->get($id);
        $first->claimForSubmission($id, ContractSamples::at('2026-11-10T10:00:00Z'));
        $stale->startSubmission(ContractSamples::at('2026-11-10T10:00:01Z'));

        try {
            $second->save($stale);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.concurrent_modification', $exception->violationCode());
            static::assertSame(['id' => 'record-1'], $exception->params());
        }
        $stored = (new DoctrineDeclarationRecordRepository($this->file->entityManager()))->get($id);
        static::assertSame('2026-11-10T10:00:00Z', $stored->lastAttemptAt()?->toIso8601());
    }

    public function testASlowSubmissionConcludedAfterTheStaleReconciliationIsRefusedAndTheRecordStaysOutcomeUnknown(): void
    {
        $this->file = new FileDatabase();
        $worker = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        $reconciler = new DoctrineDeclarationRecordRepository($this->file->entityManager());
        $worker->save(ContractSamples::pendingRecord(1));
        $id = DeclarationRecordId::fromString('record-1');
        $worker->claimForSubmission($id, ContractSamples::at('2026-11-10T10:00:00Z'));
        $slow = $worker->get($id);
        $stale = $reconciler->get($id);
        $stale->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], ContractSamples::at('2026-11-10T11:00:00Z'));
        $reconciler->save($stale);
        $slow->markRejected([], ContractSamples::at('2026-11-10T11:30:00Z'));

        try {
            $worker->save($slow);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.concurrent_modification', $exception->violationCode());
        }
        $stored = (new DoctrineDeclarationRecordRepository($this->file->entityManager()))->get($id);
        static::assertSame(DeclarationState::OutcomeUnknown, $stored->state());
        static::assertSame(OutcomeUnknownReason::StaleSubmission, $stored->outcomeUnknownReason());
    }
}
