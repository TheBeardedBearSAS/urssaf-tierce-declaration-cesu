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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Gateways;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayEstimateTest extends TestCase
{
    public function testEstimate(): void
    {
        $stack = Gateways::answering(200, Fixtures::load('estimate_response'));

        $estimate = Gateways::of($stack)->estimate(Stacks::tenant(), GatewaySamples::estimate());

        static::assertSame(10_000, $estimate->employerContributions()->cents());
        static::assertSame(5000, $estimate->employeeContributions()->cents());
        $request = Gateways::apiRequest($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/cesu/estimer', (string) $request->getUri());
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertEquals(Fixtures::decoded('estimate_request'), Gateways::jsonBody($request));
        static::assertSame(['tenant-1:estimate_contributions'], $stack->rateLimiter->acquired());
    }

    public function testAnUnreadableSuccessIsUnexpected(): void
    {
        $stack = Gateways::answering(200, '{}');

        $this->expectException(UnexpectedUrssafResponse::class);

        Gateways::of($stack)->estimate(Stacks::tenant(), GatewaySamples::estimate());
    }

    public function testATechnicalErrorIsRetryableNotUnknown(): void
    {
        $stack = Gateways::answering(500, Fixtures::load('error_technical'));

        $this->expectException(UrssafUnavailable::class);

        Gateways::of($stack)->estimate(Stacks::tenant(), GatewaySamples::estimate());
    }
}
