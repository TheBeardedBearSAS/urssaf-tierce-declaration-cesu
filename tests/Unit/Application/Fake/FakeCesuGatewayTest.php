<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class FakeCesuGatewayTest extends TestCase
{
    public function testAnUnscriptedCallThrows(): void
    {
        $gateway = new FakeCesuGateway();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unexpected registerMandate() call.');

        $gateway->registerMandate(TenantId::fromString('tenant-1'), EmployerSamples::employer());
    }

    public function testResponsesAreServedInOrderPerOperationAndJournaled(): void
    {
        $journal = new Journal();
        $gateway = new FakeCesuGateway($journal);
        $tenant = TenantId::fromString('tenant-1');
        $failure = new RuntimeException('boom');
        $gateway->willRegisterMandate();
        $gateway->willRegisterMandate($failure);
        $gateway->willCancelMandate();

        $gateway->registerMandate($tenant, EmployerSamples::employer());
        try {
            $gateway->registerMandate($tenant, EmployerSamples::employer());
            static::fail('Expected the scripted failure.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        $gateway->cancelMandate($tenant, EmployerSamples::employer());

        static::assertSame(['call:030', 'call:030', 'call:031'], $journal->entries());
        static::assertSame([Operation::RegisterMandate, Operation::RegisterMandate, Operation::CancelMandate], $gateway->calls());
        static::assertSame(['tenant-1', 'tenant-1', 'tenant-1'], $gateway->tenants());
        static::assertCount(3, $gateway->employers());
    }

    public function testAScriptedValueIsReturnedOnce(): void
    {
        $gateway = new FakeCesuGateway();
        $status = EmployerSamples::status(true);
        $gateway->willVerifyEmployer($status);

        static::assertSame($status, $gateway->verifyEmployer(
            TenantId::fromString('tenant-1'),
            EmployerVerification::employerOnly(EmployerSamples::employer()),
        ));
        static::assertCount(1, $gateway->verifications());

        $this->expectException(LogicException::class);
        $gateway->verifyEmployer(
            TenantId::fromString('tenant-1'),
            EmployerVerification::employerOnly(EmployerSamples::employer()),
        );
    }
}
