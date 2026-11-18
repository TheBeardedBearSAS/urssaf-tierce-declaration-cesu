<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Refreshes the least recently checked employers of a tenant (method 020). The batch report is discarded: a failed
 * account is left untouched by the use case and retried by the next run. Lot 6 design 7.2, lock 7.3.
 */
final readonly class SyncEmployerStatusesMessageHandler
{
    public function __construct(private SyncEmployerStatusesHandler $sync, private OperationLocks $locks) {}

    public function __invoke(SyncEmployerStatusesMessage $message): void
    {
        $this->locks->exclusively(
            'employers.sync-status',
            $message->tenantId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->sync)(new SyncEmployerStatuses(TenantId::fromString($message->tenantId), null))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
