<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\RateLimiter;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\NoopRateLimiter;

final class NoopRateLimiterTest extends TestCase
{
    public function testNeverThrottles(): void
    {
        $limiter = new NoopRateLimiter();
        $tenant = TenantId::fromString('tenant-a');

        for ($i = 0; $i < 500; ++$i) {
            $limiter->acquire($tenant, Operation::VerifyEmployer);
        }

        $this->expectNotToPerformAssertions();
    }
}
