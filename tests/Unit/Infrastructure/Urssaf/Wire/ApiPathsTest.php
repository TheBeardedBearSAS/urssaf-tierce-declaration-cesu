<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ApiPaths;

final class ApiPathsTest extends TestCase
{
    public function testPathsWithoutEmployer(): void
    {
        static::assertSame('/employeurs', ApiPaths::registerEmployer());
        static::assertSame('/salaries', ApiPaths::verifyEmployee());
        static::assertSame('/mandats', ApiPaths::mandates());
        static::assertSame('/estimer', ApiPaths::estimate());
    }

    public function testPathsOfAnEmployer(): void
    {
        $employer = CesuNumber::fromString('Z0003752390009');

        static::assertSame('/employeurs/Z0003752390009', ApiPaths::verifyEmployer($employer));
        static::assertSame('/employeurs/Z0003752390009/declarer', ApiPaths::declare($employer));
        static::assertSame('/employeurs/Z0003752390009/cesuplus', ApiPaths::activateCesuPlus($employer));
        static::assertSame('/employeurs/Z0003752390009/aici', ApiPaths::activateAici($employer));
    }
}
