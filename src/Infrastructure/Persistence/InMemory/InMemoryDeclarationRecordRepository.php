<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory;

use DateInterval;
use Override;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Process-local {@see DeclarationRecordRepository} for tests and demos.
 *
 * Identity semantics: like a database, it stores a snapshot. {@see self::save()} copies the aggregate (without its
 * pending domain events, which are never persisted) and every read returns a new copy, so a change is only visible
 * once saved. Because the stored copy is private, {@see self::claimForSubmission()} can apply
 * {@see DeclarationRecord::startSubmission()} to it without touching the events pending on the caller's instance (the
 * transition records no event, as the port requires).
 *
 * Lists are ordered as documented by the port; records that compare equal keep their insertion order.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class InMemoryDeclarationRecordRepository implements DeclarationRecordRepository
{
    /** @var array<string, DeclarationRecord> stored snapshots by record id, in insertion order */
    private array $records = [];

    /**
     * Copies of every stored record (monitoring, tests).
     *
     * @return list<DeclarationRecord>
     */
    public function all(): array
    {
        return Selection::of($this->records)->copies();
    }

    /**
     * @throws InvariantViolation "declaration_record.duplicate_external_reference" or
     *                            "declaration_record.duplicate_declaration_id"
     */
    #[Override]
    public function save(DeclarationRecord $record): void
    {
        $this->assertUnique($record);
        $snapshot = clone $record;
        $snapshot->releaseEvents();
        $this->records[$record->id()->value()] = $snapshot;
    }

    #[Override]
    public function get(DeclarationRecordId $id): DeclarationRecord
    {
        $stored = $this->records[$id->value()] ?? throw RecordNotFound::withIdentifier('declaration_record', $id->value());

        return clone $stored;
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?DeclarationRecord
    {
        return Selection::of($this->records)
            ->where(
                static fn(DeclarationRecord $record): bool => $record->tenantId()->equals($tenantId)
                    && $record->externalReference()->equals($externalReference),
            )
            ->copies()[0] ?? null;
    }

    #[Override]
    public function findByDeclarationId(TenantId $tenantId, DeclarationId $declarationId): ?DeclarationRecord
    {
        return Selection::of($this->records)
            ->where(
                static fn(DeclarationRecord $record): bool => $record->tenantId()->equals($tenantId)
                    && $record->declarationId()?->equals($declarationId) === true,
            )
            ->copies()[0] ?? null;
    }

    #[Override]
    public function findByState(TenantId $tenantId, DeclarationState $state, int $limit): array
    {
        return Selection::of($this->records)
            ->where(
                static fn(DeclarationRecord $record): bool => $record->tenantId()->equals($tenantId)
                    && $record->state() === $state,
            )
            ->sortedBy(
                static fn(DeclarationRecord $a, DeclarationRecord $b): int => Selection::compareInstants(
                    $a->updatedAt(),
                    $b->updatedAt(),
                ),
            )
            ->limit($limit)
            ->copies();
    }

    #[Override]
    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return Selection::of($this->records)
            ->where(
                static fn(DeclarationRecord $record): bool => $record->state() === DeclarationState::Submitting
                    && $record->status()->isLastAttemptStartedBefore($olderThan, new DateInterval('PT0S')),
            )
            ->sortedBy(
                static fn(DeclarationRecord $a, DeclarationRecord $b): int => Selection::compareInstants(
                    $a->lastAttemptAt(),
                    $b->lastAttemptAt(),
                ),
            )
            ->limit($limit)
            ->copies();
    }

    #[Override]
    public function countByState(TenantId $tenantId): array
    {
        $counts = [];
        foreach (DeclarationState::cases() as $state) {
            $counts[$state->value] = 0;
        }
        foreach ($this->records as $record) {
            if (!$record->tenantId()->equals($tenantId)) {
                continue;
            }
            ++$counts[$record->state()->value];
        }

        return $counts;
    }

    #[Override]
    public function tenantsWithState(DeclarationState $state): array
    {
        return array_map(
            static fn(DeclarationRecord $record): TenantId => $record->tenantId(),
            Selection::of($this->records)
                ->where(static fn(DeclarationRecord $record): bool => $record->state() === $state)
                ->distinctBy(static fn(DeclarationRecord $record): string => $record->tenantId()->value())
                ->sortedBy(
                    static fn(DeclarationRecord $a, DeclarationRecord $b): int => strcmp(
                        $a->tenantId()->value(),
                        $b->tenantId()->value(),
                    ),
                )
                ->all(),
        );
    }

    #[Override]
    public function claimForSubmission(DeclarationRecordId $id, Instant $now): bool
    {
        $stored = $this->records[$id->value()] ?? null;
        if ($stored === null || $stored->state() !== DeclarationState::Pending) {
            return false;
        }

        $stored->startSubmission($now);

        return true;
    }

    #[Override]
    public function delete(DeclarationRecordId $id): void
    {
        unset($this->records[$id->value()]);
    }

    private function assertUnique(DeclarationRecord $record): void
    {
        $declarationId = $record->declarationId();
        foreach ($this->records as $stored) {
            if ($stored->id()->equals($record->id()) || !$stored->tenantId()->equals($record->tenantId())) {
                continue;
            }
            if ($stored->externalReference()->equals($record->externalReference())) {
                throw InvariantViolation::because(
                    'declaration_record.duplicate_external_reference',
                    'A declaration record already exists for this external reference.',
                    ['external_reference' => $record->externalReference()->value()],
                );
            }
            if ($declarationId instanceof DeclarationId && $stored->declarationId()?->equals($declarationId) === true) {
                throw InvariantViolation::because(
                    'declaration_record.duplicate_declaration_id',
                    'A declaration record already exists for this declaration id.',
                    ['declaration_id' => $declarationId->value()],
                );
            }
        }
    }
}
