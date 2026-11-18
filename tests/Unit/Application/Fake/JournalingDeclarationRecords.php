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
 * Decorator over the production in-memory repository: journals every save ("save:<id>"), every won claim
 * ("claim:<id>") and every delete ("delete:<id>"); a claim can be made to lose, like another worker winning it.
 *
 * One method per method of the port.
 *
 * @mago-expect lint:too-many-methods
 */
final class JournalingDeclarationRecords implements DeclarationRecordRepository
{
    private readonly InMemoryDeclarationRecordRepository $inner;

    /** @var list<string> */
    private array $refusedClaims = [];

    public function __construct(private readonly Journal $journal = new Journal())
    {
        $this->inner = new InMemoryDeclarationRecordRepository();
    }

    public function inner(): InMemoryDeclarationRecordRepository
    {
        return $this->inner;
    }

    /**
     * Stores without journaling (test setup).
     */
    public function add(DeclarationRecord ...$records): void
    {
        foreach ($records as $record) {
            $this->inner->save($record);
        }
    }

    /**
     * Simulates another worker winning the claim: claimForSubmission() returns false for this record.
     */
    public function refuseClaim(DeclarationRecordId $id): void
    {
        $this->refusedClaims[] = $id->value();
    }

    public function save(DeclarationRecord $record): void
    {
        $this->journal->add('save:' . $record->id()->value());
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
        if (\in_array($id->value(), $this->refusedClaims, true)) {
            return false;
        }
        $claimed = $this->inner->claimForSubmission($id, $now);
        if ($claimed) {
            $this->journal->add('claim:' . $id->value());
        }

        return $claimed;
    }

    public function delete(DeclarationRecordId $id): void
    {
        $this->journal->add('delete:' . $id->value());
        $this->inner->delete($id);
    }

    public function countByEmployer(TenantId $tenantId, CesuNumber $employerCesuNumber): array
    {
        return $this->inner->countByEmployer($tenantId, $employerCesuNumber);
    }

    /**
     * Journals "delete-by-employer:<cesu number>:<states>": the states are in the entry so that a test proves which ones
     * were erasable.
     */
    public function deleteByEmployer(TenantId $tenantId, CesuNumber $employerCesuNumber, array $states): array
    {
        $this->journal->add(
            'delete-by-employer:' . $employerCesuNumber->value() . ':' . implode(',', array_map(static fn(DeclarationState $state): string => $state->value, $states)),
        );

        return $this->inner->deleteByEmployer($tenantId, $employerCesuNumber, $states);
    }
}
