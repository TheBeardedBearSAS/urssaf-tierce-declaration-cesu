<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Gateways;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayCredentialsTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation}>
     */
    public static function operations(): iterable
    {
        foreach (Operation::cases() as $operation) {
            yield $operation->methodNumber() => [$operation];
        }
    }

    private static function call(UrssafHttpGateway $gateway, Operation $operation): mixed
    {
        $tenant = Stacks::tenant();

        return match ($operation) {
            Operation::RegisterEmployer => $gateway->registerEmployer($tenant, EmployerSamples::newEmployer()),
            Operation::VerifyEmployer => $gateway->verifyEmployer($tenant, EmployerVerification::employerOnly(GatewaySamples::employer())),
            Operation::VerifyEmployee => $gateway->verifyEmployee($tenant, EmployeeSamples::byCesuNumber()),
            Operation::RegisterMandate => $gateway->registerMandate($tenant, GatewaySamples::employer()),
            Operation::CancelMandate => $gateway->cancelMandate($tenant, GatewaySamples::employer()),
            Operation::EstimateContributions => $gateway->estimate($tenant, GatewaySamples::estimate()),
            Operation::DeclareSalary => $gateway->declare($tenant, DeclarationSamples::standard()),
            Operation::ActivateCesuPlus => $gateway->activateCesuPlus($tenant, GatewaySamples::cesuPlusActivation(true)),
            Operation::ActivateAici => $gateway->activateAici($tenant, GatewaySamples::aiciActivation()),
        };
    }

    #[DataProvider('operations')]
    public function testAnUnknownTenantSendsNothing(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $gateway = Gateways::withCredentials($stack, new InMemoryCredentialsProvider([]));

        try {
            self::call($gateway, $operation);
            static::fail('The unknown tenant must be refused.');
        } catch (RecordNotFound $notFound) {
            static::assertSame('credentials.not_found', $notFound->violationCode());
        }

        static::assertSame([], $stack->requests());
        static::assertSame([], $stack->rateLimiter->acquired());
    }
}
