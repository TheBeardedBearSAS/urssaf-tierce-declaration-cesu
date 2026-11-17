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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Gateways;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayAiciTest extends TestCase
{
    public function testActivateAici(): void
    {
        $stack = Gateways::answering(200, Fixtures::load('activate_aici_response'));

        $activation = Gateways::of($stack)->activateAici(Stacks::tenant(), GatewaySamples::aiciActivation());

        static::assertTrue($activation->isActive());
        $request = Gateways::apiRequest($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/cesu/employeurs/Z0003752390009/aici',
            (string) $request->getUri(),
        );
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertEquals(Fixtures::decoded('activate_aici_request_assembled'), Gateways::jsonBody($request));
        static::assertSame(['tenant-1:activate_aici'], $stack->rateLimiter->acquired());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function unreadableAnswers(): iterable
    {
        yield '200 empty object' => [200, '{}'];
        yield '201 invalid JSON' => [201, 'not json'];
    }

    #[DataProvider('unreadableAnswers')]
    public function testAnUnreadableSuccessIsAnUnknownOutcome(int $status, string $body): void
    {
        $stack = Gateways::answering($status, $body);

        try {
            Gateways::of($stack)->activateAici(Stacks::tenant(), GatewaySamples::aiciActivation());
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $unknown) {
            static::assertSame(Operation::ActivateAici, $unknown->operation());
            static::assertSame($status, $unknown->httpStatus());
            static::assertInstanceOf(UnexpectedUrssafResponse::class, $unknown->getPrevious());
        }
    }
}
