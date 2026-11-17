<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\TenantOverview;

final class TenantOverviewTest extends TestCase
{
    public function testOnlyTheThreeDeclarationStatesNeedAttention(): void
    {
        $overview = new TenantOverview(
            'tenant-1',
            ['registered' => 50, 'mandate_cancelled' => 60],
            ['notified' => 70, 'confirmed' => 80, 'cancelled' => 90],
            [
                'pending' => 1000,
                'submitting' => 2000,
                'declared' => 4000,
                'rejected' => 8000,
                'already_declared' => 9000,
                'outcome_unknown' => 1,
                'error' => 20,
                'invalid_credentials' => 300,
            ],
        );

        static::assertSame(321, $overview->needingAttention());
    }

    public function testToArrayKeepsTheKeysInOrder(): void
    {
        $employers = ['registered' => 1];
        $mandates = ['notified' => 2];
        $declarations = ['outcome_unknown' => 3, 'error' => 0, 'invalid_credentials' => 1];
        $overview = new TenantOverview('tenant-1', $employers, $mandates, $declarations);

        static::assertSame('tenant-1', $overview->tenantId);
        static::assertSame([
            'tenant_id' => 'tenant-1',
            'employers' => $employers,
            'mandates' => $mandates,
            'declarations' => $declarations,
            'needing_attention' => 4,
        ], $overview->toArray());
    }
}
