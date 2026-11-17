<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;

/**
 * Methods 060 and 070 of the simulator.
 */
final class SimulatedCesuGatewayActivationTest extends TestCase
{
    public function testCesuPlusIsActivatedForTheMandatedPair(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $employee = CesuNumber::fromString('00000123456789');

        $activation = $gateway->activateCesuPlus(SimulatorSamples::tenant1(), GatewaySamples::cesuPlusActivation(true));

        static::assertTrue($activation->isActive());
        static::assertTrue($gateway->isCesuPlusActive(SimulatorSamples::cesuNumber(), $employee));
        static::assertFalse($gateway->isCesuPlusActive(SimulatorSamples::cesuNumber(), CesuNumber::fromString('00000999999999')));
        static::assertSame([Operation::ActivateCesuPlus], $gateway->calls());
    }

    public function testCesuPlusOfAnUnknownEmployerIsRefused(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $request = CesuPlusActivationRequest::of(
            SimulatorSamples::unknownEmployer(),
            CesuNumber::fromString('00000123456789'),
            true,
        );

        $rejected = SimulatorSamples::rejection(static fn(): CesuPlusActivation => $gateway->activateCesuPlus(SimulatorSamples::tenant1(), $request));

        static::assertTrue($rejected->is(BusinessError::EmployerCesuNumberUnknown));
        static::assertSame(Operation::ActivateCesuPlus, $rejected->operation());
    }

    public function testCesuPlusWithAWrongBirthDateIsRefused(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $request = CesuPlusActivationRequest::of(
            SimulatorSamples::employerBornElsewhen(),
            CesuNumber::fromString('00000123456789'),
            true,
        );

        $rejected = SimulatorSamples::rejection(static fn(): CesuPlusActivation => $gateway->activateCesuPlus(SimulatorSamples::tenant1(), $request));

        static::assertTrue($rejected->is(BusinessError::EmployerCesuNumberUnknown));
    }

    public function testCesuPlusWithoutMandateIsRefused(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $rejected = SimulatorSamples::rejection(static fn(): CesuPlusActivation => $gateway->activateCesuPlus(SimulatorSamples::tenant2(), GatewaySamples::cesuPlusActivation(true)));

        static::assertTrue($rejected->isMandateMissing());
    }

    public function testCesuPlusAlreadyActiveIsRefused(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->activateCesuPlus(SimulatorSamples::tenant1(), GatewaySamples::cesuPlusActivation(true));

        $rejected = SimulatorSamples::rejection(static fn(): CesuPlusActivation => $gateway->activateCesuPlus(SimulatorSamples::tenant1(), GatewaySamples::cesuPlusActivation(true)));

        static::assertTrue($rejected->is(BusinessError::CesuPlusAlreadyActive));
    }

    public function testAnotherEmployeeCanBeActivated(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->activateCesuPlus(SimulatorSamples::tenant1(), GatewaySamples::cesuPlusActivation(true));
        $other = CesuPlusActivationRequest::of(
            SimulatorSamples::employer(),
            CesuNumber::fromString('00000999999999'),
            true,
        );

        $activation = $gateway->activateCesuPlus(SimulatorSamples::tenant1(), $other);

        static::assertTrue($activation->isActive());
    }

    public function testAiciOfAnUnknownEmployerIsRefused(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $request = SimulatorSamples::aiciRequestFor(SimulatorSamples::unknownEmployer());

        $rejected = SimulatorSamples::rejection(static fn(): AiciActivation => $gateway->activateAici(SimulatorSamples::tenant1(), $request));

        static::assertTrue($rejected->is(BusinessError::EmployerNotFound));
        static::assertSame(Operation::ActivateAici, $rejected->operation());
    }

    public function testAiciWithAWrongBirthDateIsRefused(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $request = SimulatorSamples::aiciRequestFor(SimulatorSamples::employerBornElsewhen());

        $rejected = SimulatorSamples::rejection(static fn(): AiciActivation => $gateway->activateAici(SimulatorSamples::tenant1(), $request));

        static::assertTrue($rejected->is(BusinessError::EmployerNotFound));
    }

    public function testAiciWithoutCesuPlusIsRefused(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $rejected = SimulatorSamples::rejection(static fn(): AiciActivation => $gateway->activateAici(SimulatorSamples::tenant1(), SimulatorSamples::aiciRequest()));

        static::assertTrue($rejected->is(BusinessError::CesuPlusNotActive));
        static::assertFalse($gateway->isAiciActive(SimulatorSamples::cesuNumber()));
    }

    public function testAiciIsActivatedAndIdempotent(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->activateCesuPlus(SimulatorSamples::tenant1(), GatewaySamples::cesuPlusActivation(true));

        $first = $gateway->activateAici(SimulatorSamples::tenant1(), SimulatorSamples::aiciRequest());
        $second = $gateway->activateAici(SimulatorSamples::tenant1(), SimulatorSamples::aiciRequest());

        static::assertTrue($first->isActive());
        static::assertTrue($second->isActive());
        static::assertTrue($gateway->isAiciActive(SimulatorSamples::cesuNumber()));
    }
}
