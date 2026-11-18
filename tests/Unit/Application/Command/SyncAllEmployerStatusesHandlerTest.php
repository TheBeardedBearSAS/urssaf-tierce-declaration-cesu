<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncAllEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncAllEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class SyncAllEmployerStatusesHandlerTest extends TestCase
{
    public function testSyncsEveryTenantAndReportsRefusedCredentials(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->account(2, EmployerStage::Registered, Harness::OTHER_TENANT);
        $harness->account(3, EmployerStage::Registered, 'tenant-3');
        $harness->account(4, EmployerStage::Registered, 'tenant-3');
        $harness->gateway->willVerifyEmployer(UrssafAuthenticationFailed::withStatus(401));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));

        $report = self::handler($harness)(new SyncAllEmployerStatuses(null));

        static::assertEquals([
            new BatchItem('tenant-1', ItemOutcome::InvalidCredentials),
            new BatchItem('account-2', ItemOutcome::Changed),
            new BatchItem('account-3', ItemOutcome::Changed),
            new BatchItem('account-4', ItemOutcome::Changed),
        ], $report->items());
        static::assertSame(['tenant-1', 'tenant-2', 'tenant-3', 'tenant-3'], $harness->gateway->tenants());
    }

    public function testTheLimitIsPerTenant(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->account(2);
        $harness->account(3, EmployerStage::Registered, Harness::OTHER_TENANT);
        $harness->account(4, EmployerStage::Registered, Harness::OTHER_TENANT);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));

        $report = self::handler($harness)(new SyncAllEmployerStatuses(1));

        static::assertSame(2, $report->total());
        static::assertSame([Harness::TENANT, Harness::OTHER_TENANT], $harness->gateway->tenants());
    }

    public function testTemporaryFailuresPropagate(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->gateway->willVerifyEmployer(UrssafUnavailable::withStatus(Operation::VerifyEmployer, 503, null, ApiErrors::none()));

        $this->expectException(UrssafUnavailable::class);

        self::handler($harness)(new SyncAllEmployerStatuses(null));
    }

    public function testNoTenantNoItem(): void
    {
        $harness = new Harness();

        static::assertSame([], self::handler($harness)(new SyncAllEmployerStatuses(null))->items());
    }

    private static function handler(Harness $harness): SyncAllEmployerStatusesHandler
    {
        return new SyncAllEmployerStatusesHandler(
            $harness->accounts,
            new SyncEmployerStatusesHandler(
                $harness->accounts,
                new EmployerStatusRefresher($harness->gateway, $harness->mandates, $harness->accounts, $harness->clock, $harness->unitOfWork),
                $harness->policy,
            ),
        );
    }
}
