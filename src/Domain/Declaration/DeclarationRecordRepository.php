<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Port: persistence of {@see DeclarationRecord}. Uniqueness expected: id; (tenant, external reference) (ADR 0006);
 * (tenant, declaration id) when set.
 */
interface DeclarationRecordRepository
{
    /**
     * Inserts or updates. Domain events are not persisted: release and publish them separately.
     */
    public function save(DeclarationRecord $record): void;

    /**
     * @throws RecordNotFound "declaration_record.not_found"
     */
    public function get(DeclarationRecordId $id): DeclarationRecord;

    /**
     * Idempotency of DeclareSalary: the same external reference with the same content returns the existing record.
     */
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?DeclarationRecord;

    public function findByDeclarationId(TenantId $tenantId, DeclarationId $declarationId): ?DeclarationRecord;

    /**
     * Least recently updated first.
     *
     * @param positive-int $limit
     *
     * @return list<DeclarationRecord>
     */
    public function findByState(TenantId $tenantId, DeclarationState $state, int $limit): array;

    /**
     * Every tenant, state submitting, last attempt at or before $olderThan (= now - stale submission delay), oldest
     * attempt first (ReconcileStaleDeclarations).
     *
     * @param positive-int $limit
     *
     * @return list<DeclarationRecord>
     */
    public function findStaleSubmissions(Instant $olderThan, int $limit): array;

    /**
     * Number of records per state (GetWorkflowOverview): state value => count, every state present (0 when none).
     *
     * @return array<string, int>
     */
    public function countByState(TenantId $tenantId): array;

    /**
     * Tenants having at least one record in the state (RequeueAfterInvalidCredentials, schedulers).
     *
     * @return list<TenantId>
     */
    public function tenantsWithState(DeclarationState $state): array;

    /**
     * Compare-and-set pending -> submitting: one more attempt, last attempt and update at now, exactly like
     * {@see DeclarationRecord::startSubmission()}. True when this call won, false when the record is missing or not
     * pending. No event is recorded: reload the record with {@see self::get()}.
     */
    public function claimForSubmission(DeclarationRecordId $id, Instant $now): bool;

    /**
     * GDPR erasure; nothing happens when the record does not exist.
     */
    public function delete(DeclarationRecordId $id): void;
}
