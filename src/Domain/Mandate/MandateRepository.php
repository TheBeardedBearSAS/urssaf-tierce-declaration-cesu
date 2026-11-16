<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Persistence port of the {@see Mandate} aggregate. Expected uniqueness: the id, and at most one active mandate per
 * (tenant, Cesu number) (ADR 0006).
 */
interface MandateRepository
{
    /**
     * Inserts or updates. Domain events are not persisted: release and publish them separately.
     */
    public function save(Mandate $mandate): void;

    /**
     * @throws RecordNotFound "mandate.not_found"
     */
    public function get(MandateId $id): Mandate;

    /**
     * The non-cancelled mandate of the employer, if any (mandate guard, method 031, status synchronisation).
     */
    public function findActive(TenantId $tenantId, CesuNumber $cesuNumber): ?Mandate;

    /**
     * Every mandate of the employer, oldest notification first.
     *
     * @return list<Mandate>
     */
    public function findByEmployer(TenantId $tenantId, CesuNumber $cesuNumber): array;

    /**
     * GDPR erasure (ForgetEmployer, lot 10 design §2.1): removes the mandate and, in the Doctrine bridge, its
     * active-mandate key row, so that the employer can hold a new active mandate afterwards. Nothing happens when the
     * mandate does not exist. No event.
     */
    public function delete(MandateId $id): void;
}
