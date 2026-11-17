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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;

/**
 * Decorator over the production in-memory repository: journals every save as "save:<id>" and every delete as "delete:<id>".
 */
final readonly class JournalingMandates implements MandateRepository
{
    private InMemoryMandateRepository $inner;

    public function __construct(private Journal $journal = new Journal())
    {
        $this->inner = new InMemoryMandateRepository();
    }

    public function inner(): InMemoryMandateRepository
    {
        return $this->inner;
    }

    /**
     * Stores without journaling (test setup).
     */
    public function add(Mandate ...$mandates): void
    {
        foreach ($mandates as $mandate) {
            $this->inner->save($mandate);
        }
    }

    public function save(Mandate $mandate): void
    {
        $this->journal->add('save:' . $mandate->id()->value());
        $this->inner->save($mandate);
    }

    public function get(MandateId $id): Mandate
    {
        return $this->inner->get($id);
    }

    public function findActive(TenantId $tenantId, CesuNumber $cesuNumber): ?Mandate
    {
        return $this->inner->findActive($tenantId, $cesuNumber);
    }

    public function findByEmployer(TenantId $tenantId, CesuNumber $cesuNumber): array
    {
        return $this->inner->findByEmployer($tenantId, $cesuNumber);
    }

    public function delete(MandateId $id): void
    {
        $this->journal->add('delete:' . $id->value());
        $this->inner->delete($id);
    }
}
