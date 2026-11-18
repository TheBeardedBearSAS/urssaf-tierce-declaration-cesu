<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Override;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\CesuNumberType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\EmployerAccountIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Doctrine ORM implementation of {@see EmployerAccountRepository} (after the reference
 * DoctrineCustomerRegistrationRepository, lot 7 design §5), with the semantics of the in-memory one: uniqueness
 * violations are reported as {@see InvariantViolation} with the same codes, lists are ordered as the port says (never
 * checked accounts first, then oldest check, creation and id).
 *
 * Unit of work: {@see self::save()} persists and flushes immediately (a flush writes every dirty managed entity), so a
 * save inside a {@see DoctrineTransactionManager} transaction is durable only when that transaction commits. An
 * aggregate that is not managed (created by the caller, or detached by clear()) replaces the stored row of the same
 * identity, like a save in the in-memory repository. Reads return the managed instance, so unlike the in-memory
 * repository a change is visible to every holder of the instance before it is saved.
 *
 * Concurrency: the aggregate carries a version column (Doctrine optimistic lock). Saving a managed instance that
 * another process changed since it was read is refused with "employer_account.concurrent_modification" (the entity
 * manager is then closed by the ORM, like after any failed flush).
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness and helpers.
 * @mago-expect lint:too-many-methods
 */
final readonly class DoctrineEmployerAccountRepository implements EmployerAccountRepository
{
    private const ENTITY = EmployerAccount::class;

    private const CONCURRENT_MODIFICATION = 'employer_account.concurrent_modification';

    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @throws InvariantViolation "employer_account.duplicate_cesu_number",
     *                            "employer_account.duplicate_external_reference" or
     *                            "employer_account.concurrent_modification"
     */
    #[Override]
    public function save(EmployerAccount $account): void
    {
        $this->assertUnique($account);
        $replaces = !$this->entityManager->contains($account) && $this->isStored($account->id());
        try {
            $this->entityManager->getConnection()->transactional(function () use ($account, $replaces): void {
                if ($replaces) {
                    $this->discard($account->id());
                }
                $this->entityManager->persist($account);
                $this->entityManager->flush();
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent writer won the race between the check above and the flush.
            $this->assertUnique($account);

            throw $exception;
        } catch (OptimisticLockException) {
            // The domain exception carries no previous throwable: the Doctrine one only says that no row matched.
            $this->refuseConcurrentModification($account->id()->value());
        }
    }

    #[Override]
    public function get(EmployerAccountId $id): EmployerAccount
    {
        return $this->entityManager->find(self::ENTITY, $id) ?? throw RecordNotFound::withIdentifier(
            'employer_account',
            $id->value(),
        );
    }

    #[Override]
    public function findByCesuNumber(TenantId $tenantId, CesuNumber $cesuNumber): ?EmployerAccount
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery('SELECT a FROM ' . self::ENTITY . ' a WHERE a.tenantId = :tenant AND a.employer.cesuNumber = :cesu')
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('cesu', $cesuNumber, CesuNumberType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?EmployerAccount
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery('SELECT a FROM ' . self::ENTITY . ' a WHERE a.tenantId = :tenant AND a.externalReference = :reference')
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('reference', $externalReference, ExternalReferenceType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findLeastRecentlyChecked(TenantId $tenantId, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT a, CASE WHEN a.tracking.lastCheckedAt IS NULL THEN 0 ELSE 1 END AS HIDDEN checkedRank FROM ' . self::ENTITY . ' a'
                    . ' WHERE a.tenantId = :tenant'
                    . ' ORDER BY checkedRank ASC, a.tracking.lastCheckedAt ASC, a.createdAt ASC, a.id ASC',
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function tenants(): array
    {
        /** @var list<string> $tenants */
        $tenants = $this->entityManager
            ->createQuery('SELECT DISTINCT a.tenantId FROM ' . self::ENTITY . ' a ORDER BY a.tenantId ASC')
            ->getSingleColumnResult();

        return array_map(TenantId::fromString(...), $tenants);
    }

    #[Override]
    public function delete(EmployerAccountId $id): void
    {
        $this->discard($id);
    }

    /**
     * @throws InvariantViolation always
     */
    private function refuseConcurrentModification(string $id): never
    {
        throw InvariantViolation::because(
            self::CONCURRENT_MODIFICATION,
            'The employer account was changed by another process since it was read: read it again.',
            ['id' => $id],
        );
    }

    private function detach(EmployerAccountId $id): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $managed = $unitOfWork->tryGetById($id, self::ENTITY);
        if ($managed !== false) {
            $unitOfWork->detach($managed);
        }
    }

    private function isStored(EmployerAccountId $id): bool
    {
        return $this->entityManager
            ->createQuery('SELECT a.id FROM ' . self::ENTITY . ' a WHERE a.id = :id')
            ->setParameter('id', $id, EmployerAccountIdType::NAME)
            ->getScalarResult() !== [];
    }

    /**
     * Removes the stored account (and its managed copy) immediately: deletion, or replacement by an unmanaged aggregate
     * of the same identity (a deferred EntityManager::remove() would run after the inserts of the same flush).
     */
    private function discard(EmployerAccountId $id): void
    {
        $this->detach($id);
        $this->entityManager
            ->createQuery('DELETE FROM ' . self::ENTITY . ' a WHERE a.id = :id')
            ->setParameter('id', $id, EmployerAccountIdType::NAME)
            ->execute();
    }

    /**
     * @throws InvariantViolation
     */
    private function assertUnique(EmployerAccount $account): void
    {
        if ($this->exists($account, 'a.employer.cesuNumber = :value', $account->cesuNumber()->value())) {
            throw InvariantViolation::because(
                'employer_account.duplicate_cesu_number',
                'An employer account already exists for this Cesu number.',
                ['cesu_number' => $account->cesuNumber()->value()],
            );
        }
        if ($this->exists($account, 'a.externalReference = :value', $account->externalReference()->value())) {
            throw InvariantViolation::because(
                'employer_account.duplicate_external_reference',
                'An employer account already exists for this external reference.',
                ['external_reference' => $account->externalReference()->value()],
            );
        }
    }

    private function exists(EmployerAccount $account, string $condition, string $value): bool
    {
        return $this->entityManager
            ->createQuery('SELECT a.id FROM ' . self::ENTITY . ' a WHERE a.tenantId = :tenant AND a.id <> :id AND ' . $condition)
            ->setParameter('tenant', $account->tenantId(), TenantIdType::NAME)
            ->setParameter('id', $account->id(), EmployerAccountIdType::NAME)
            ->setParameter('value', $value)
            ->getScalarResult() !== [];
    }
}
