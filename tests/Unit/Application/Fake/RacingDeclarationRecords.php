<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;

/**
 * Decorator over an in-memory repository: deleteByEmployer() deletes, then saves a prepared record of the same employer,
 * like a claim or an insert of another process that won the race (lot 10 design §2.4). Every other method delegates.
 *
 * One method per method of the port.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class RacingDeclarationRecords implements DeclarationRecordRepository
{
    public function __construct(
        private InMemoryDeclarationRecordRepository $inner,
        private DeclarationRecord $winner,
    ) {}

    public function save(DeclarationRecord $record): void
    {
        $this->inner->save($record);
    }

    public function get(DeclarationRecordId $id): DeclarationRecord
    {
        return $this->inner->get($id);
    }

    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?DeclarationRecord
    {
        return $this->inner->findByExternalReference($tenantId, $externalReference);
    }

    public function findByDeclarationId(TenantId $tenantId, DeclarationId $declarationId): ?DeclarationRecord
    {
        return $this->inner->findByDeclarationId($tenantId, $declarationId);
    }

    public function findByState(TenantId $tenantId, DeclarationState $state, int $limit): array
    {
        return $this->inner->findByState($tenantId, $state, $limit);
    }

    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return $this->inner->findStaleSubmissions($olderThan, $limit);
    }

    public function countByState(TenantId $tenantId): array
    {
        return $this->inner->countByState($tenantId);
    }

    public function tenantsWithState(DeclarationState $state): array
    {
        return $this->inner->tenantsWithState($state);
    }

    public function claimForSubmission(DeclarationRecordId $id, Instant $now): bool
    {
        return $this->inner->claimForSubmission($id, $now);
    }

    public function delete(DeclarationRecordId $id): void
    {
        $this->inner->delete($id);
    }

    public function countByEmployer(TenantId $tenantId, CesuNumber $employerCesuNumber): array
    {
        return $this->inner->countByEmployer($tenantId, $employerCesuNumber);
    }

    public function deleteByEmployer(TenantId $tenantId, CesuNumber $employerCesuNumber, array $states): array
    {
        $deleted = $this->inner->deleteByEmployer($tenantId, $employerCesuNumber, $states);
        $this->inner->save($this->winner);

        return $deleted;
    }
}
