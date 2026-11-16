<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Override;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Entity\ActiveMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\CesuNumberType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\MandateIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Doctrine ORM implementation of {@see MandateRepository} (lot 7 design §5.4), with the semantics of the in-memory one:
 * at most one active (non-cancelled) mandate per employer, reported as {@see InvariantViolation}
 * "mandate.duplicate_active" and enforced by the database through the key table of {@see ActiveMandate} (primary key
 * (tenant, Cesu number)), rewritten with DBAL in the transaction of every save.
 *
 * Unit of work: {@see self::save()} persists and flushes immediately (a flush writes every dirty managed entity), so a
 * save inside a {@see DoctrineTransactionManager} transaction is durable only when that transaction commits. An
 * aggregate that is not managed (created by the caller, or detached by clear()) replaces the stored row of the same
 * identity, like a save in the in-memory repository. Reads return the managed instance, so unlike the in-memory
 * repository a change is visible to every holder of the instance before it is saved.
 *
 * Concurrency: the aggregate carries a version column (Doctrine optimistic lock); saving a managed instance that
 * another process changed since it was read is refused with "mandate.concurrent_modification".
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness, key row and helpers.
 * @mago-expect lint:too-many-methods
 */
final readonly class DoctrineMandateRepository implements MandateRepository
{
    private const ENTITY = Mandate::class;

    private const CONCURRENT_MODIFICATION = 'mandate.concurrent_modification';

    private const BY_NOTIFICATION = ' ORDER BY m.timeline.notifiedAt ASC, m.id ASC';

    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @throws InvariantViolation "mandate.duplicate_active" or "mandate.concurrent_modification"
     */
    #[Override]
    public function save(Mandate $mandate): void
    {
        $this->assertSingleActive($mandate);
        try {
            $this->store($mandate);
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent writer won the race between the check above and the key row (or the flush): the flushed
            // mandate was rolled back with the transaction, so the managed copy must not outlive it.
            $this->detach($mandate->id());
            $this->assertSingleActive($mandate);

            throw $exception;
        } catch (OptimisticLockException) {
            // The domain exception carries no previous throwable: the Doctrine one only says that no row matched.
            $this->refuseConcurrentModification($mandate->id()->value());
        }
    }

    #[Override]
    public function get(MandateId $id): Mandate
    {
        return $this->entityManager->find(self::ENTITY, $id) ?? throw RecordNotFound::withIdentifier(
            'mandate',
            $id->value(),
        );
    }

    #[Override]
    public function findActive(TenantId $tenantId, CesuNumber $cesuNumber): ?Mandate
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery(
                    'SELECT m FROM ' . self::ENTITY . ' m WHERE m.tenantId = :tenant AND m.employer.cesuNumber = :cesu'
                    . ' AND m.state IN (:active)' . self::BY_NOTIFICATION,
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('cesu', $cesuNumber, CesuNumberType::NAME)
                ->setParameter('active', self::activeStates(), ArrayParameterType::STRING)
                ->toIterable(),
            self::ENTITY,
        );
    }

    #[Override]
    public function findByEmployer(TenantId $tenantId, CesuNumber $cesuNumber): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT m FROM ' . self::ENTITY . ' m WHERE m.tenantId = :tenant AND m.employer.cesuNumber = :cesu'
                    . self::BY_NOTIFICATION,
                )
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('cesu', $cesuNumber, CesuNumberType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    /**
     * GDPR erasure: the mandate and its key row go in one transaction (nothing happens for an unknown mandate), so that
     * the employer can hold a new active mandate afterwards.
     */
    #[Override]
    public function delete(MandateId $id): void
    {
        $this->connection()->transactional(function () use ($id): void {
            $this->discard($id);
            $this->connection()->delete($this->keyTable(), ['mandate_id' => $id], ActiveMandate::TYPES);
        });
    }

    /**
     * @throws InvariantViolation always
     */
    private function refuseConcurrentModification(string $id): never
    {
        throw InvariantViolation::because(
            self::CONCURRENT_MODIFICATION,
            'The mandate was changed by another process since it was read: read it again.',
            ['id' => $id],
        );
    }

    /**
     * The raw values of the active (non-cancelled) states, derived from the domain enum so that the SQL selection
     * cannot drift from {@see MandateState::isActive()}.
     *
     * @return array<int, string>
     */
    private static function activeStates(): array
    {
        return array_map(
            static fn(MandateState $state): string => $state->value,
            array_filter(MandateState::cases(), static fn(MandateState $state): bool => $state->isActive()),
        );
    }

    private function store(Mandate $mandate): void
    {
        $replaces = !$this->entityManager->contains($mandate) && $this->isStored($mandate->id());
        $this->connection()->transactional(function () use ($mandate, $replaces): void {
            if ($replaces) {
                $this->discard($mandate->id());
            }
            $this->entityManager->persist($mandate);
            $this->entityManager->flush();
            $this->replaceKey($mandate);
        });
    }

    private function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    private function keyTable(): string
    {
        return $this->entityManager->getClassMetadata(ActiveMandate::class)->getTableName();
    }

    /**
     * Rewrites the key row of the mandate (delete and insert: no existence check, no driver-specific "affected rows"
     * semantics). A concurrent second active mandate of the employer fails on the primary key.
     */
    private function replaceKey(Mandate $mandate): void
    {
        $this->connection()->delete($this->keyTable(), ['mandate_id' => $mandate->id()], ActiveMandate::TYPES);
        if (!$mandate->isActive()) {
            return;
        }
        $this->connection()->insert($this->keyTable(), ActiveMandate::of($mandate)->row(), ActiveMandate::TYPES);
    }

    private function detach(MandateId $id): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $managed = $unitOfWork->tryGetById($id, self::ENTITY);
        if ($managed !== false) {
            $unitOfWork->detach($managed);
        }
    }

    private function isStored(MandateId $id): bool
    {
        return $this->entityManager
            ->createQuery('SELECT m.id FROM ' . self::ENTITY . ' m WHERE m.id = :id')
            ->setParameter('id', $id, MandateIdType::NAME)
            ->getScalarResult() !== [];
    }

    /**
     * Removes the stored mandate (and its managed copy) that an unmanaged aggregate of the same identity replaces; its
     * key row is rewritten by {@see self::replaceKey()}.
     */
    private function discard(MandateId $id): void
    {
        $this->detach($id);
        $this->entityManager
            ->createQuery('DELETE FROM ' . self::ENTITY . ' m WHERE m.id = :id')
            ->setParameter('id', $id, MandateIdType::NAME)
            ->execute();
    }

    /**
     * @throws InvariantViolation
     */
    private function assertSingleActive(Mandate $mandate): void
    {
        if (!$mandate->isActive()) {
            return;
        }
        $others = $this->entityManager
            ->createQuery(
                'SELECT m.id FROM ' . self::ENTITY . ' m WHERE m.tenantId = :tenant AND m.employer.cesuNumber = :cesu'
                . ' AND m.id <> :id AND m.state IN (:active)',
            )
            ->setParameter('tenant', $mandate->tenantId(), TenantIdType::NAME)
            ->setParameter('cesu', $mandate->cesuNumber(), CesuNumberType::NAME)
            ->setParameter('id', $mandate->id(), MandateIdType::NAME)
            ->setParameter('active', self::activeStates(), ArrayParameterType::STRING)
            ->getScalarResult();
        if ($others !== []) {
            throw InvariantViolation::because(
                'mandate.duplicate_active',
                'An active mandate already exists for this employer.',
                ['cesu_number' => $mandate->cesuNumber()->value()],
            );
        }
    }
}
