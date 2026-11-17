<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Port;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RetryableException;

final class RateLimitExceededTest extends TestCase
{
    public function testMessageAndAccessors(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::DeclareSalary, 12);

        static::assertSame(Operation::DeclareSalary, $exception->operation());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertSame('Local rate limit reached for URSSAF method 050; retry in 12 second(s).', $exception->getMessage());
        static::assertContains(RetryableException::class, class_implements($exception));
    }

    public function testNegativeDelaysAreClampedToZero(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::RegisterEmployer, -3);

        static::assertSame(0, $exception->retryAfterSeconds());
        static::assertSame('Local rate limit reached for URSSAF method 010; retry in 0 second(s).', $exception->getMessage());
        static::assertSame(0, RateLimitExceeded::forOperation(Operation::RegisterEmployer, 0)->retryAfterSeconds());
    }
}
