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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;

/**
 * Decorator over the production in-memory repository (its queries are proven by the repository contract): journals
 * every save as "save:<id>" so that handler tests assert the order of the writes.
 */
final readonly class JournalingEmployerAccounts implements EmployerAccountRepository
{
    private InMemoryEmployerAccountRepository $inner;

    public function __construct(private Journal $journal = new Journal())
    {
        $this->inner = new InMemoryEmployerAccountRepository();
    }

    public function inner(): InMemoryEmployerAccountRepository
    {
        return $this->inner;
    }

    /**
     * Stores without journaling (test setup).
     */
    public function add(EmployerAccount ...$accounts): void
    {
        foreach ($accounts as $account) {
            $this->inner->save($account);
        }
    }

    public function save(EmployerAccount $account): void
    {
        $this->journal->add('save:' . $account->id()->value());
        $this->inner->save($account);
    }

    public function get(EmployerAccountId $id): EmployerAccount
    {
        return $this->inner->get($id);
    }

    public function findByCesuNumber(TenantId $tenantId, CesuNumber $cesuNumber): ?EmployerAccount
    {
        return $this->inner->findByCesuNumber($tenantId, $cesuNumber);
    }

    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?EmployerAccount
    {
        return $this->inner->findByExternalReference($tenantId, $externalReference);
    }

    public function findLeastRecentlyChecked(TenantId $tenantId, int $limit): array
    {
        return $this->inner->findLeastRecentlyChecked($tenantId, $limit);
    }

    public function tenants(): array
    {
        return $this->inner->tenants();
    }

    public function delete(EmployerAccountId $id): void
    {
        $this->journal->add('delete:' . $id->value());
        $this->inner->delete($id);
    }
}
