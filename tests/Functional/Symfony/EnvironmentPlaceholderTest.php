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
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;

/**
 * The documented configuration reads the environment from an environment variable: it is resolved at runtime.
 */
final class EnvironmentPlaceholderTest extends TestCase
{
    public function testTheEnvironmentMayComeFromAnEnvironmentVariable(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'production';
        $_SERVER['URSSAF_TEST_ENVIRONMENT'] = 'production';
        try {
            $configuration = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%'])
                ->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT'], $_SERVER['URSSAF_TEST_ENVIRONMENT']);
        }

        static::assertSame(Environment::Production, $configuration->environment());
    }

    public function testTheAgreementEncodingMayComeFromAnEnvironmentVariable(): void
    {
        $_ENV['URSSAF_TEST_ENCODING'] = 'numeric';
        $_SERVER['URSSAF_TEST_ENCODING'] = 'numeric';
        try {
            $configuration = Harness::boot(['agreement_encoding' => '%env(URSSAF_TEST_ENCODING)%'])
                ->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);
        } finally {
            unset($_ENV['URSSAF_TEST_ENCODING'], $_SERVER['URSSAF_TEST_ENCODING']);
        }

        static::assertSame('numeric', $configuration->agreementEncoding()->value);
    }
}
