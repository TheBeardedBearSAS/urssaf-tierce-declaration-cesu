<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RetryableException;

/**
 * Runs {@see SyncEmployerStatusesHandler} for each tenant having tracked employers. A tenant whose credentials are
 * refused is reported ({@see ItemOutcome::InvalidCredentials}, subject = tenant id) and the run goes on with the next
 * tenant; temporary failures propagate.
 */
final readonly class SyncAllEmployerStatusesHandler
{
    public function __construct(
        private EmployerAccountRepository $accounts,
        private SyncEmployerStatusesHandler $syncTenant,
    ) {}

    /**
     * @throws RetryableException
     */
    public function __invoke(SyncAllEmployerStatuses $command): BatchReport
    {
        $report = BatchReport::of();
        foreach ($this->accounts->tenants() as $tenantId) {
            try {
                $tenantReport = ($this->syncTenant)(new SyncEmployerStatuses($tenantId, $command->limitPerTenant));
            } catch (UrssafAuthenticationFailed) {
                $tenantReport = BatchReport::of(new BatchItem($tenantId->value(), ItemOutcome::InvalidCredentials));
            }
            $report = $report->merge($tenantReport);
        }

        return $report;
    }
}
