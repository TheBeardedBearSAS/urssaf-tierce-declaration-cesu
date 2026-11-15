<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory;

use Override;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Process-local {@see EmployerAccountRepository} for tests and demos.
 *
 * Identity semantics: like a database, it stores a snapshot. {@see self::save()} copies the aggregate (without its
 * pending domain events, which are never persisted) and every read returns a new copy, so a change is only visible
 * once saved and the events pending on the caller's instance are never consumed.
 *
 * Insertion order is kept for ties.
 */
final class InMemoryEmployerAccountRepository implements EmployerAccountRepository
{
    /** @var array<string, EmployerAccount> stored snapshots by account id, in insertion order */
    private array $accounts = [];

    /**
     * Copies of every stored account (monitoring, tests).
     *
     * @return list<EmployerAccount>
     */
    public function all(): array
    {
        return Selection::of($this->accounts)->copies();
    }

    /**
     * @throws InvariantViolation "employer_account.duplicate_cesu_number" or
     *                            "employer_account.duplicate_external_reference"
     */
    #[Override]
    public function save(EmployerAccount $account): void
    {
        $this->assertUnique($account);
        $snapshot = clone $account;
        $snapshot->releaseEvents();
        $this->accounts[$account->id()->value()] = $snapshot;
    }

    #[Override]
    public function get(EmployerAccountId $id): EmployerAccount
    {
        $stored = $this->accounts[$id->value()] ?? throw RecordNotFound::withIdentifier('employer_account', $id->value());

        return clone $stored;
    }

    #[Override]
    public function findByCesuNumber(TenantId $tenantId, CesuNumber $cesuNumber): ?EmployerAccount
    {
        return Selection::of($this->accounts)
            ->where(
                static fn(EmployerAccount $account): bool => $account->tenantId()->equals($tenantId)
                    && $account->cesuNumber()->equals($cesuNumber),
            )
            ->copies()[0] ?? null;
    }

    #[Override]
    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?EmployerAccount
    {
        return Selection::of($this->accounts)
            ->where(
                static fn(EmployerAccount $account): bool => $account->tenantId()->equals($tenantId)
                    && $account->externalReference()->equals($externalReference),
            )
            ->copies()[0] ?? null;
    }

    #[Override]
    public function findLeastRecentlyChecked(TenantId $tenantId, int $limit): array
    {
        return Selection::of($this->accounts)
            ->where(static fn(EmployerAccount $account): bool => $account->tenantId()->equals($tenantId))
            ->sortedBy(
                static fn(EmployerAccount $a, EmployerAccount $b): int => Selection::compareInstants(
                    $a->lastCheckedAt(),
                    $b->lastCheckedAt(),
                ),
            )
            ->limit($limit)
            ->copies();
    }

    #[Override]
    public function tenants(): array
    {
        return array_map(
            static fn(EmployerAccount $account): TenantId => $account->tenantId(),
            Selection::of($this->accounts)
                ->distinctBy(static fn(EmployerAccount $account): string => $account->tenantId()->value())
                ->sortedBy(
                    static fn(EmployerAccount $a, EmployerAccount $b): int => strcmp(
                        $a->tenantId()->value(),
                        $b->tenantId()->value(),
                    ),
                )
                ->all(),
        );
    }

    #[Override]
    public function delete(EmployerAccountId $id): void
    {
        unset($this->accounts[$id->value()]);
    }

    private function assertUnique(EmployerAccount $account): void
    {
        $others = Selection::of($this->accounts)->where(
            static fn(EmployerAccount $stored): bool => !$stored->id()->equals($account->id())
                && $stored->tenantId()->equals($account->tenantId()),
        )->all();
        foreach ($others as $stored) {
            if ($stored->cesuNumber()->equals($account->cesuNumber())) {
                throw InvariantViolation::because(
                    'employer_account.duplicate_cesu_number',
                    'An employer account already exists for this Cesu number.',
                    ['cesu_number' => $account->cesuNumber()->value()],
                );
            }
        }
        foreach ($others as $stored) {
            if ($stored->externalReference()->equals($account->externalReference())) {
                throw InvariantViolation::because(
                    'employer_account.duplicate_external_reference',
                    'An employer account already exists for this external reference.',
                    ['external_reference' => $account->externalReference()->value()],
                );
            }
        }
    }
}
