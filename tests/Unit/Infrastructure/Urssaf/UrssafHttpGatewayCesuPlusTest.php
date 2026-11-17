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

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Gateways;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayCesuPlusTest extends TestCase
{
    /**
     * @return iterable<string, array{AgreementEncoding, bool, string}>
     */
    public static function agreements(): iterable
    {
        yield 'boolean true' => [AgreementEncoding::Boolean, true, 'true'];
        yield 'boolean false' => [AgreementEncoding::Boolean, false, 'false'];
        yield 'numeric true' => [AgreementEncoding::Numeric, true, '1'];
        yield 'numeric false' => [AgreementEncoding::Numeric, false, '0'];
    }

    #[DataProvider('agreements')]
    public function testActivateCesuPlus(AgreementEncoding $encoding, bool $agreement, string $expected): void
    {
        $stack = Stacks::configured(
            new ApiConfiguration(Environment::Sandbox, null, null, null, 60, ApiConfiguration::DEFAULT_USER_AGENT, new DateTimeZone('Europe/Paris'), $encoding),
        );
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('activate_cesu_plus_response'));

        $activation = Gateways::of($stack)->activateCesuPlus(Stacks::tenant(), GatewaySamples::cesuPlusActivation($agreement));

        static::assertTrue($activation->isActive());
        $request = Gateways::apiRequest($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/cesu/employeurs/Z0003752390009/cesuplus'
            . '?dateNaissance=1975-01-30&numeroCesuSalarie=00000123456789&accord=' . $expected,
            (string) $request->getUri(),
        );
        static::assertSame('', (string) $request->getBody());
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame(['tenant-1:activate_cesu_plus'], $stack->rateLimiter->acquired());
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
            Gateways::of($stack)->activateCesuPlus(Stacks::tenant(), GatewaySamples::cesuPlusActivation(true));
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $unknown) {
            static::assertSame(Operation::ActivateCesuPlus, $unknown->operation());
            static::assertSame($status, $unknown->httpStatus());
            static::assertInstanceOf(UnexpectedUrssafResponse::class, $unknown->getPrevious());
        }
    }
}
