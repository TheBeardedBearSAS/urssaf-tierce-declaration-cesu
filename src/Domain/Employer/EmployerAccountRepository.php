<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Persistence port of the {@see EmployerAccount} aggregate. Expected uniqueness: the id, (tenant, Cesu number) (ADR
 * 0006) and (tenant, external reference).
 */
interface EmployerAccountRepository
{
    /**
     * Inserts or updates. Domain events are not persisted: release and publish them separately.
     */
    public function save(EmployerAccount $account): void;

    /**
     * @throws RecordNotFound "employer_account.not_found"
     */
    public function get(EmployerAccountId $id): EmployerAccount;

    public function findByCesuNumber(TenantId $tenantId, CesuNumber $cesuNumber): ?EmployerAccount;

    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?EmployerAccount;

    /**
     * Accounts of the tenant for the status synchronisation (method 020, the only polling): never checked first, then
     * by last check ascending.
     *
     * @param positive-int $limit
     *
     * @return list<EmployerAccount>
     */
    public function findLeastRecentlyChecked(TenantId $tenantId, int $limit): array;

    /**
     * Distinct tenants owning at least one account.
     *
     * @return list<TenantId>
     */
    public function tenants(): array;

    /**
     * GDPR erasure; nothing happens when the account does not exist.
     */
    public function delete(EmployerAccountId $id): void;
}
