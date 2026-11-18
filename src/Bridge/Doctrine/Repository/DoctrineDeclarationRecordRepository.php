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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Override;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\CesuNumberType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\DeclarationIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\DeclarationRecordIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Doctrine ORM implementation of {@see DeclarationRecordRepository} (after the reference
 * DoctrineCustomerRegistrationRepository, lot 7 design §5.3), with the semantics of the in-memory one: lists are
 * ordered by last update (oldest first, then creation, then id) unless the port says otherwise, uniqueness violations
 * are reported as {@see InvariantViolation} with the same codes.
 *
 * Unit of work: {@see self::save()} persists and flushes immediately (a flush writes every dirty managed entity), so a
 * save inside a {@see DoctrineTransactionManager} transaction is durable only when that transaction commits. An
 * aggregate that is not managed (created by the caller, or detached by clear()) replaces the stored row of the same
 * identity, like a save in the in-memory repository: a "supersede" (delete, then save of a new record with the same
 * external reference, ADR 0006) inside one transaction is therefore atomic. Reads return the managed instance, so
 * unlike the in-memory repository a change is visible to every holder of the instance before it is saved.
 *
 * Claiming: {@see self::claimForSubmission()} is one conditional UPDATE that also bumps the version column, never an
 * object round-trip. Concurrency: saving a managed instance that another process changed since it was read (for
 * instance the stale reconciliation claimed or concluded it) is refused with
 * "declaration_record.concurrent_modification".
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness, claim and erasure helpers,
 * hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect
 */
final readonly class DoctrineDeclarationRecordRepository implements DeclarationRecordRepository
{
    private const ENTITY = DeclarationRecord::class;

    private const CONCURRENT_MODIFICATION = 'declaration_record.concurrent_modification';

    private const BY_UPDATE = ' ORDER BY r.status.updatedAt ASC, r.createdAt ASC, r.id ASC';

    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @throws InvariantViolation "declaration_record.duplicate_external_reference",
     *                            "declaration_record.duplicate_declaration_id" or
     *                            "declaration_record.concurrent_modification"
     */
    #[Override]
    public function save(DeclarationRecord $record): void
    {
        $this->assertUnique($record);
        $replaces = !$this->entityManager->contains($record) && $this->isStored($record->id());
        try {
            $this->entityManager->getConnection()->transactional(function () use ($record, $replaces): void {
                if ($replaces) {
                    $this->discard($record->id());
                }
                $this->entityManager->persist($record);
                $this->entityManager->flush();
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent writer won the race between the check above and the flush.
            $this->assertUnique($record);

            throw $exception;
        } catch (OptimisticLockException) {
            // The domain exception carries no previous throwable: the Doctrine one only says that no row matched.
            $this->refuseConcurrentModification($record->id()->value());
        }
    }

    #[Override]
    public function get(DeclarationRecordId $id): DeclarationRecord
    {
        return $this->entityManager->find(self::ENTITY, $id) ?? throw RecordNotFound::withIdentifier(
            'declaration_record',
            $id->value(),
        );
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?DeclarationRecord
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery('SELECT r FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.externalReference = :reference')
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('reference', $externalReference, ExternalReferenceType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findByDeclarationId(TenantId $tenantId, DeclarationId $declarationId): ?DeclarationRecord
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery('SELECT r FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.status.declarationId = :declarationId')
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('declarationId', $declarationId, DeclarationIdType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findByState(TenantId $tenantId, DeclarationState $state, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery('SELECT r FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.status.state = :state' . self::BY_UPDATE)
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('state', $state)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT r FROM ' . self::ENTITY . ' r WHERE r.status.state = :submitting AND r.status.lastAttemptAt <= :olderThan'
                    . ' ORDER BY r.status.lastAttemptAt ASC, r.createdAt ASC, r.id ASC',
                )
                ->setParameter('submitting', DeclarationState::Submitting)
                ->setParameter('olderThan', $olderThan, InstantType::NAME)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function countByState(TenantId $tenantId): array
    {
        $counts = [];
        foreach (DeclarationState::cases() as $state) {
            $counts[$state->value] = 0;
        }
        /** @var list<array{state: DeclarationState, total: int}> $rows */
        $rows = $this->entityManager
            ->createQuery('SELECT r.status.state AS state, COUNT(r.id) AS total FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant GROUP BY r.status.state')
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->getResult();
        foreach ($rows as $row) {
            $counts[$row['state']->value] = $row['total'];
        }

        return $counts;
    }

    #[Override]
    public function tenantsWithState(DeclarationState $state): array
    {
        /** @var list<string> $tenants */
        $tenants = $this->entityManager
            ->createQuery('SELECT DISTINCT r.tenantId FROM ' . self::ENTITY . ' r WHERE r.status.state = :state ORDER BY r.tenantId ASC')
            ->setParameter('state', $state)
            ->getSingleColumnResult();

        return array_map(TenantId::fromString(...), $tenants);
    }

    #[Override]
    public function delete(DeclarationRecordId $id): void
    {
        $this->discard($id);
    }

    #[Override]
    public function countByEmployer(TenantId $tenantId, CesuNumber $employerCesuNumber): array
    {
        $counts = [];
        foreach (DeclarationState::cases() as $state) {
            $counts[$state->value] = 0;
        }
        /** @var list<array{state: DeclarationState, total: int}> $rows */
        $rows = $this->entityManager
            ->createQuery(
                'SELECT r.status.state AS state, COUNT(r.id) AS total FROM ' . self::ENTITY . ' r'
                . ' WHERE r.tenantId = :tenant AND r.snapshot.employerCesuNumber = :cesu GROUP BY r.status.state',
            )
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->setParameter('cesu', $employerCesuNumber, CesuNumberType::NAME)
            ->getResult();
        foreach ($rows as $row) {
            $counts[$row['state']->value] = $row['total'];
        }

        return $counts;
    }

    #[Override]
    public function deleteByEmployer(TenantId $tenantId, CesuNumber $employerCesuNumber, array $states): array
    {
        $values = array_map(static fn(DeclarationState $state): string => $state->value, $states);
        $condition = ' r WHERE r.tenantId = :tenant AND r.snapshot.employerCesuNumber = :cesu AND r.status.state IN (:states)';
        /** @var list<string> $selected */
        $selected = $this->entityManager
            ->createQuery('SELECT r.id FROM ' . self::ENTITY . $condition . ' ORDER BY r.createdAt ASC, r.id ASC')
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->setParameter('cesu', $employerCesuNumber, CesuNumberType::NAME)
            ->setParameter('states', $values, ArrayParameterType::STRING)
            ->getSingleColumnResult();
        $ids = array_map(DeclarationRecordId::fromString(...), $selected);
        foreach ($ids as $id) {
            $this->detach($id);
        }
        $this->entityManager
            ->createQuery('DELETE FROM ' . self::ENTITY . $condition)
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->setParameter('cesu', $employerCesuNumber, CesuNumberType::NAME)
            ->setParameter('states', $values, ArrayParameterType::STRING)
            ->execute();

        return $ids;
    }

    #[Override]
    public function claimForSubmission(DeclarationRecordId $id, Instant $now): bool
    {
        $claimed = $this->entityManager
            ->createQuery(
                'UPDATE ' . self::ENTITY . ' r SET r.status.state = :submitting, r.status.attempts = r.status.attempts + 1,'
                . ' r.status.lastAttemptAt = :now, r.status.updatedAt = :now, r.status.outcomeUnknownReason = NULL,'
                . ' r.version = r.version + 1'
                . ' WHERE r.id = :id AND r.status.state = :pending',
            )
            ->setParameter('submitting', DeclarationState::Submitting)
            ->setParameter('pending', DeclarationState::Pending)
            ->setParameter('now', $now, InstantType::NAME)
            ->setParameter('id', $id, DeclarationRecordIdType::NAME)
            ->execute() === 1;
        if ($claimed) {
            // The managed copy (if any) is now stale: drop it so that the next get() reads the claimed row.
            $this->detach($id);
        }

        return $claimed;
    }

    /**
     * @throws InvariantViolation always
     */
    private function refuseConcurrentModification(string $id): never
    {
        throw InvariantViolation::because(
            self::CONCURRENT_MODIFICATION,
            'The declaration record was changed by another process since it was read: read it again.',
            ['id' => $id],
        );
    }

    private function detach(DeclarationRecordId $id): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $managed = $unitOfWork->tryGetById($id, self::ENTITY);
        if ($managed !== false) {
            $unitOfWork->detach($managed);
        }
    }

    private function isStored(DeclarationRecordId $id): bool
    {
        return $this->entityManager
            ->createQuery('SELECT r.id FROM ' . self::ENTITY . ' r WHERE r.id = :id')
            ->setParameter('id', $id, DeclarationRecordIdType::NAME)
            ->getScalarResult() !== [];
    }

    /**
     * Removes the stored record (and its managed copy) immediately: deletion, or replacement by an unmanaged aggregate
     * of the same identity (a deferred EntityManager::remove() would run after the inserts of the same flush, and
     * break the supersede of a record by a new one with the same external reference).
     */
    private function discard(DeclarationRecordId $id): void
    {
        $this->detach($id);
        $this->entityManager
            ->createQuery('DELETE FROM ' . self::ENTITY . ' r WHERE r.id = :id')
            ->setParameter('id', $id, DeclarationRecordIdType::NAME)
            ->execute();
    }

    /**
     * @throws InvariantViolation
     */
    private function assertUnique(DeclarationRecord $record): void
    {
        if ($this->exists($record, 'r.externalReference = :value', $record->externalReference()->value())) {
            throw InvariantViolation::because(
                'declaration_record.duplicate_external_reference',
                'A declaration record already exists for this external reference.',
                ['external_reference' => $record->externalReference()->value()],
            );
        }
        $declarationId = $record->declarationId();
        if ($declarationId instanceof DeclarationId && $this->exists($record, 'r.status.declarationId = :value', $declarationId->value())) {
            throw InvariantViolation::because(
                'declaration_record.duplicate_declaration_id',
                'A declaration record already exists for this declaration id.',
                ['declaration_id' => $declarationId->value()],
            );
        }
    }

    private function exists(DeclarationRecord $record, string $condition, string $value): bool
    {
        return $this->entityManager
            ->createQuery('SELECT r.id FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.id <> :id AND ' . $condition)
            ->setParameter('tenant', $record->tenantId(), TenantIdType::NAME)
            ->setParameter('id', $record->id(), DeclarationRecordIdType::NAME)
            ->setParameter('value', $value)
            ->getScalarResult() !== [];
    }
}
