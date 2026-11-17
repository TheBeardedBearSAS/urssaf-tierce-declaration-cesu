<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\BundleConfiguration;

/**
 * The default persistence driver follows the installed ORM (lot 7 design §8.1, ADR 0013 amendment).
 */
final class DefaultDriverTest extends TestCase
{
    public function testTheDriverIsDoctrineWhenTheOrmIsInstalled(): void
    {
        static::assertSame('doctrine', BundleConfiguration::defaultDriverFor(true));
    }

    public function testTheDriverIsInMemoryWithoutTheOrm(): void
    {
        static::assertSame('in_memory', BundleConfiguration::defaultDriverFor(false));
    }

    public function testTheRuntimeDefaultIsDoctrineBecauseTheOrmIsADevelopmentDependency(): void
    {
        static::assertSame('doctrine', BundleConfiguration::defaultDriver());
    }
}
