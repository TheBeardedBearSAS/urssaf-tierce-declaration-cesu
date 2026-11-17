<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\EmployerAccountRepositoryContract;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\BeforeNextFlush;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\FileDatabase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * Contract and Doctrine-specific scenarios of the employer account repository: one method per scenario.
 * @mago-expect lint:too-many-methods
 */
final class DoctrineEmployerAccountRepositoryTest extends TestCase
{
    use EmployerAccountRepositoryContract {
        setUp as private contractSetUp;
    }

    private EntityManagerInterface $entityManager;

    private ?FileDatabase $file = null;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->contractSetUp();
    }

    protected function createRepository(): EmployerAccountRepository
    {
        return new DoctrineEmployerAccountRepository($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->file?->delete();
    }

    public function testItReturnsTheManagedInstanceUntilTheEntityManagerIsCleared(): void
    {
        $account = ContractSamples::pendingAccount(1);
        $this->repository->save($account);
        $id = EmployerAccountId::fromString('account-1');

        static::assertSame($account, $this->repository->get($id));

        $this->entityManager->clear();
        $reloaded = $this->repository->get($id);

        static::assertNotSame($account, $reloaded);
        static::assertSame([], $reloaded->releaseEvents());
        $account->releaseEvents();
        static::assertEquals($account, $reloaded);
    }

    public function testAnUnmanagedAccountReplacesTheStoredRowOfTheSameIdentity(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $this->entityManager->clear();

        $this->repository->save(ContractSamples::account(1, EmployerStage::MandateNotified));
        $this->entityManager->clear();

        static::assertSame(
            EmployerStage::MandateNotified,
            $this->repository->get(EmployerAccountId::fromString('account-1'))->stage(),
        );
        static::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM urssaf_cesu_employer_accounts'));
    }

    public function testAChangeOfAManagedAccountIsWrittenOnSaveAndTheSameInstanceCanBeSavedAgain(): void
    {
        $account = ContractSamples::account(1);
        $this->repository->save($account);
        $account->markMandateNotified(ContractSamples::at('2026-11-11T09:00:00Z'));
        $this->repository->save($account);
        $account->markMandateCancelled(ContractSamples::at('2026-11-12T09:00:00Z'));
        $this->repository->save($account);
        $this->repository->save($account);
        $this->entityManager->clear();

        static::assertSame(EmployerStage::MandateCancelled, $this->repository->get($account->id())->stage());
        static::assertSame(3, $this->entityManager->getConnection()->fetchOne('SELECT version FROM urssaf_cesu_employer_accounts'));
    }

    public function testARefusedDuplicateDoesNotCloseTheEntityManager(): void
    {
        $this->repository->save(ContractSamples::pendingAccount(1));

        try {
            $this->repository->save(ContractSamples::pendingAccountOfEmployer(1, 2));
            static::fail('Expected a violation.');
        } catch (InvariantViolation) {
            static::assertTrue($this->entityManager->isOpen());
        }
    }

    public function testNeverCheckedAccountsComeFirstThenByCheckInstantCreationAndIdentifierWhateverTheInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::checkedAccount(1, '2026-11-12T00:00:00Z'));
        $this->repository->save(ContractSamples::pendingAccount(4, ContractSamples::TENANT, '2026-11-03T00:00:00Z'));
        $this->repository->save(ContractSamples::pendingAccount(3, ContractSamples::TENANT, '2026-11-02T00:00:00Z'));
        $this->repository->save(ContractSamples::pendingAccount(2, ContractSamples::TENANT, '2026-11-03T00:00:00Z'));

        $accounts = $this->repository->findLeastRecentlyChecked(ContractSamples::tenant(), 10);

        static::assertSame(
            ['account-3', 'account-2', 'account-4', 'account-1'],
            array_map(static fn(EmployerAccount $account): string => $account->id()->value(), $accounts),
        );
    }

    public function testAConcurrentDuplicateCesuNumberIsReportedAsAViolation(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineEmployerAccountRepository($entityManager);
        $rival = new DoctrineEmployerAccountRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::pendingAccountOfEmployer(1, 2));
        });

        try {
            $repository->save(ContractSamples::pendingAccountOfEmployer(1, 1));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.duplicate_cesu_number', $exception->violationCode());
        }
    }

    public function testAConcurrentDuplicateExternalReferenceIsReportedAsAViolation(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineEmployerAccountRepository($entityManager);
        $rival = new DoctrineEmployerAccountRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            // The reference of "employer-1" with another Cesu number: only the external reference collides.
            $rival->save(EmployerAccount::register(
                EmployerAccountId::fromString('account-2'),
                ContractSamples::tenant(),
                ExternalReference::fromString('employer-1'),
                ContractSamples::employer(2),
                ContractSamples::at(),
            ));
        });

        try {
            $repository->save(ContractSamples::pendingAccountOfEmployer(1, 1));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.duplicate_external_reference', $exception->violationCode());
        }
    }

    public function testAnUnexplainedConstraintViolationIsRethrownUnchanged(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineEmployerAccountRepository($entityManager);
        $rival = new DoctrineEmployerAccountRepository($this->file->entityManager());
        // Same identifier, different tenant, Cesu number and reference: only the primary key collides.
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::pendingAccount(1, ContractSamples::OTHER_TENANT));
        });

        $this->expectException(UniqueConstraintViolationException::class);
        $repository->save(ContractSamples::pendingAccountOfEmployer(2, 1));
    }

    public function testASaveOfACopyReadBeforeAnotherProcessChangedTheAccountIsRefused(): void
    {
        $this->file = new FileDatabase();
        $first = new DoctrineEmployerAccountRepository($this->file->entityManager());
        $second = new DoctrineEmployerAccountRepository($this->file->entityManager());
        $first->save(ContractSamples::account(1));
        $id = EmployerAccountId::fromString('account-1');
        $stale = $second->get($id);
        $fresh = $first->get($id);
        $fresh->markMandateNotified(ContractSamples::at('2026-11-11T09:00:00Z'));
        $first->save($fresh);
        $stale->markMandateCancelled(ContractSamples::at('2026-11-12T09:00:00Z'));

        try {
            $second->save($stale);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.concurrent_modification', $exception->violationCode());
            static::assertSame(['id' => 'account-1'], $exception->params());
        }
        static::assertSame(EmployerStage::MandateNotified, (new DoctrineEmployerAccountRepository($this->file->entityManager()))->get($id)->stage());
    }

    public function testASaveOfACopyWhoseRowWasDeletedMeanwhileIsRefused(): void
    {
        $this->file = new FileDatabase();
        $first = new DoctrineEmployerAccountRepository($this->file->entityManager());
        $second = new DoctrineEmployerAccountRepository($this->file->entityManager());
        $first->save(ContractSamples::account(1));
        $id = EmployerAccountId::fromString('account-1');
        $stale = $second->get($id);
        $first->delete($id);
        $stale->markMandateNotified(ContractSamples::at('2026-11-11T09:00:00Z'));

        try {
            $second->save($stale);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.concurrent_modification', $exception->violationCode());
        }
    }

    public function testTheTenantsAreListedOnce(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $this->repository->save(ContractSamples::account(2));

        static::assertEquals([TenantId::fromString('tenant-1')], $this->repository->tenants());
    }
}
