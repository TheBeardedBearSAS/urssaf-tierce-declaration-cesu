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
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\SlidingWindowRateLimiter;

/**
 * What the quota is counted over: one tenant, every operation together (ADR 0011).
 */
final class SlidingWindowRateLimiterScopeTest extends TestCase
{
    private const START = '2026-01-01T10:00:00+00:00';

    public function testQuotaIsSharedByAllOperationsOfATenant(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)), 3, 10);
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $limiter->acquire($tenant, Operation::DeclareSalary);
        $limiter->acquire($tenant, Operation::RegisterMandate);

        $this->expectExceptionObject(RateLimitExceeded::forOperation(Operation::VerifyEmployee, 10));

        $limiter->acquire($tenant, Operation::VerifyEmployee);
    }

    public function testTenantsAreIsolated(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)), 1, 10);
        $limiter->acquire(TenantId::fromString('tenant-a'), Operation::VerifyEmployer);

        $limiter->acquire(TenantId::fromString('tenant-b'), Operation::VerifyEmployer);

        $this->expectException(RateLimitExceeded::class);

        $limiter->acquire(TenantId::fromString('tenant-a'), Operation::VerifyEmployer);
    }

    public function testExceptionNamesTheOperationOfTheRefusedCall(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)), 1, 10);
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::VerifyEmployer);

        try {
            $limiter->acquire($tenant, Operation::ActivateAici);
            static::fail('The limit should be exceeded.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(Operation::ActivateAici, $exception->operation());
        }
    }

    public function testLimitAndWindowOfOneAreAccepted(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)), 1, 1);
        $tenant = TenantId::fromString('tenant-a');

        $limiter->acquire($tenant, Operation::VerifyEmployer);

        $this->expectExceptionObject(RateLimitExceeded::forOperation(Operation::VerifyEmployer, 1));

        $limiter->acquire($tenant, Operation::VerifyEmployer);
    }
}
