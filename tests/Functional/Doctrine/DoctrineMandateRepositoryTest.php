<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\MandateRepositoryContract;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\BeforeNextFlush;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\FileDatabase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * Contract and Doctrine-specific scenarios of the mandate repository: one method per scenario.
 * @mago-expect lint:too-many-methods
 */
final class DoctrineMandateRepositoryTest extends TestCase
{
    use MandateRepositoryContract {
        setUp as private contractSetUp;
    }

    private EntityManagerInterface $entityManager;

    private ?FileDatabase $file = null;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->contractSetUp();
    }

    protected function createRepository(): MandateRepository
    {
        return new DoctrineMandateRepository($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->file?->delete();
    }

    /**
     * @phpstan-impure
     *
     * @return list<array<string, mixed>>
     */
    private static function keys(EntityManagerInterface $entityManager): array
    {
        return $entityManager->getConnection()->fetchAllAssociative(
            'SELECT tenant_id, cesu_number, mandate_id FROM urssaf_cesu_active_mandates ORDER BY mandate_id',
        );
    }

    public function testItReturnsTheManagedInstanceUntilTheEntityManagerIsCleared(): void
    {
        $mandate = ContractSamples::pendingMandate(1);
        $this->repository->save($mandate);
        $id = MandateId::fromString('mandate-1');

        static::assertSame($mandate, $this->repository->get($id));

        $this->entityManager->clear();
        $reloaded = $this->repository->get($id);

        static::assertNotSame($mandate, $reloaded);
        static::assertSame([], $reloaded->releaseEvents());
        $mandate->releaseEvents();
        static::assertEquals($mandate, $reloaded);
    }

    public function testTheKeyRowFollowsEverySave(): void
    {
        $mandate = ContractSamples::pendingMandate(1);
        $cesuNumber = ContractSamples::cesuNumber(1)->value();

        static::assertSame([], self::keys($this->entityManager));

        $this->repository->save($mandate);
        $notified = self::keys($this->entityManager);
        $mandate->applyStatus(true, ContractSamples::at('2026-11-11T09:00:00Z'));
        $this->repository->save($mandate);
        $confirmed = self::keys($this->entityManager);
        $mandate->cancel(ContractSamples::at('2026-11-12T09:00:00Z'));
        $this->repository->save($mandate);
        $cancelled = self::keys($this->entityManager);
        $this->repository->save(ContractSamples::pendingMandateOfEmployer(1, 2));
        $renewed = self::keys($this->entityManager);

        static::assertSame([['tenant_id' => 'tenant-1', 'cesu_number' => $cesuNumber, 'mandate_id' => 'mandate-1']], $notified);
        static::assertSame($notified, $confirmed);
        static::assertSame([], $cancelled);
        static::assertSame([['tenant_id' => 'tenant-1', 'cesu_number' => $cesuNumber, 'mandate_id' => 'mandate-2']], $renewed);
    }

    public function testDeletingAMandateRemovesItsKeyRowAndKeepsTheOthers(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Notified));
        $this->repository->save(ContractSamples::mandateOfEmployer(2, 2, MandateState::Confirmed));

        $this->repository->delete(MandateId::fromString('mandate-1'));

        static::assertSame(
            [['tenant_id' => 'tenant-1', 'cesu_number' => ContractSamples::cesuNumber(2)->value(), 'mandate_id' => 'mandate-2']],
            self::keys($this->entityManager),
        );
    }

    public function testADeleteInsideATransactionThatThenFailsKeepsTheMandateAndItsKeyRow(): void
    {
        $this->repository->save(ContractSamples::mandate(1));
        $keys = self::keys($this->entityManager);

        try {
            (new DoctrineTransactionManager($this->entityManager))->transactional(function (): never {
                $this->repository->delete(MandateId::fromString('mandate-1'));

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertSame($keys, self::keys($this->entityManager));
        static::assertSame(['mandate-1'], $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM urssaf_cesu_mandates'));
    }

    public function testAnUnmanagedMandateReplacesTheStoredRowAndRewritesTheKeyRow(): void
    {
        $this->repository->save(ContractSamples::mandate(1, MandateState::Confirmed));
        $this->entityManager->clear();

        $this->repository->save(ContractSamples::mandate(1, MandateState::Cancelled));
        $this->entityManager->clear();

        static::assertSame(MandateState::Cancelled, $this->repository->get(MandateId::fromString('mandate-1'))->state());
        static::assertSame([], self::keys($this->entityManager));
        static::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM urssaf_cesu_mandates'));
    }

    public function testAnotherInstanceOfAManagedMandateReplacesItAndTheKeyRow(): void
    {
        $this->repository->save(ContractSamples::pendingMandate(1));

        $this->repository->save(ContractSamples::mandate(1, MandateState::Cancelled));

        $this->entityManager->clear();
        static::assertSame(MandateState::Cancelled, $this->repository->get(MandateId::fromString('mandate-1'))->state());
        static::assertSame([], self::keys($this->entityManager));
    }

    public function testTheMandatesOfAnEmployerAreListedByNotificationWhateverTheIdentifiers(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 9, MandateState::Cancelled, ContractSamples::TENANT, '2026-11-01T09:00:00Z'));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Confirmed, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 5, MandateState::Cancelled, ContractSamples::TENANT, '2026-11-05T09:00:00Z'));

        static::assertSame(
            ['mandate-9', 'mandate-5', 'mandate-1'],
            self::mandateIds($this->repository->findByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1))),
        );
        static::assertSame('mandate-1', $this->repository->findActive(ContractSamples::tenant(), ContractSamples::cesuNumber(1))?->id()->value());
    }

    public function testTheActiveMandateIsTheFirstNotifiedThenTheLowestIdentifierWhenARepairedDatabaseHoldsSeveral(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 9, MandateState::Cancelled));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Cancelled));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 5, MandateState::Cancelled, ContractSamples::TENANT, '2026-11-20T09:00:00Z'));
        $this->entityManager->getConnection()->executeStatement("UPDATE urssaf_cesu_mandates SET state = 'confirmed'");
        $this->entityManager->clear();

        static::assertSame('mandate-1', $this->repository->findActive(ContractSamples::tenant(), ContractSamples::cesuNumber(1))?->id()->value());
    }

    public function testACancelledMandateIsSavedAgainWhileAnotherOneIsActive(): void
    {
        $cancelled = ContractSamples::pendingMandate(1);
        $this->repository->save($cancelled);
        $cancelled->cancel(ContractSamples::at('2026-11-11T09:00:00Z'));
        $this->repository->save($cancelled);
        $this->repository->save(ContractSamples::pendingMandateOfEmployer(1, 2));

        $this->repository->save($cancelled);

        static::assertSame(['mandate-2'], array_column(self::keys($this->entityManager), 'mandate_id'));
    }

    public function testARefusedSecondActiveMandateIsRefusedBeforeAnyFlush(): void
    {
        $this->repository->save(ContractSamples::pendingMandate(1));
        $flushed = false;
        BeforeNextFlush::run($this->entityManager, static function () use (&$flushed): void {
            $flushed = true;
        });

        try {
            $this->repository->save(ContractSamples::pendingMandateOfEmployer(1, 2));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('mandate.duplicate_active', $exception->violationCode());
        }

        static::assertFalse($flushed);
    }

    public function testMandatesNotifiedAtTheSameInstantAreListedByIdentifierWhateverTheInsertionOrder(): void
    {
        foreach ([3, 1, 2] as $number) {
            $this->repository->save(ContractSamples::mandateOfEmployer(1, $number, MandateState::Cancelled));
        }

        static::assertSame(
            ['mandate-1', 'mandate-2', 'mandate-3'],
            self::mandateIds($this->repository->findByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1))),
        );
    }

    public function testAConcurrentSecondActiveMandateIsReportedAsAViolationAndForgotten(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineMandateRepository($entityManager);
        $rival = new DoctrineMandateRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::pendingMandateOfEmployer(1, 2));
        });
        $mandate = ContractSamples::pendingMandateOfEmployer(1, 1);

        try {
            $repository->save($mandate);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('mandate.duplicate_active', $exception->violationCode());
        }
        static::assertTrue($entityManager->isOpen());
        static::assertFalse($entityManager->contains($mandate));
        $stored = $this->file->entityManager();
        static::assertSame(['mandate-2'], array_column(self::keys($stored), 'mandate_id'));
        static::assertSame(1, $stored->getConnection()->fetchOne('SELECT COUNT(*) FROM urssaf_cesu_mandates'));
    }

    public function testAnUnexplainedConstraintViolationIsRethrownUnchanged(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineMandateRepository($entityManager);
        $rival = new DoctrineMandateRepository($this->file->entityManager());
        // Same identifier, other tenant: only the primary key of the mandates collides.
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::pendingMandate(1, ContractSamples::OTHER_TENANT));
        });

        $this->expectException(UniqueConstraintViolationException::class);
        $repository->save(ContractSamples::pendingMandate(1));
    }

    public function testASaveOfACopyReadBeforeAnotherProcessChangedTheMandateIsRefused(): void
    {
        $this->file = new FileDatabase();
        $first = new DoctrineMandateRepository($this->file->entityManager());
        $second = new DoctrineMandateRepository($this->file->entityManager());
        $first->save(ContractSamples::pendingMandate(1));
        $id = MandateId::fromString('mandate-1');
        $stale = $second->get($id);
        $fresh = $first->get($id);
        $fresh->applyStatus(true, ContractSamples::at('2026-11-11T09:00:00Z'));
        $first->save($fresh);
        $stale->cancel(ContractSamples::at('2026-11-12T09:00:00Z'));

        try {
            $second->save($stale);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('mandate.concurrent_modification', $exception->violationCode());
            static::assertSame(['id' => 'mandate-1'], $exception->params());
        }
        static::assertSame(MandateState::Confirmed, (new DoctrineMandateRepository($this->file->entityManager()))->get($id)->state());
    }
}
