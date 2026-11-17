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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\CesuPlusState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\BodyReader;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Gateways;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayEmployerTest extends TestCase
{
    public function testRegisterEmployer(): void
    {
        $stack = Gateways::answering(200, Fixtures::load('register_employer_response'));
        $employer = EmployerSamples::newEmployer();

        $cesuNumber = Gateways::of($stack)->registerEmployer(Stacks::tenant(), $employer);

        static::assertSame('Z0003752390009', $cesuNumber->value());
        $request = Gateways::apiRequest($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/cesu/employeurs', (string) $request->getUri());
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        // The PDF example sends an empty "lieuDit": an absent locality is omitted instead (api-reference §2.1).
        $expected = Fixtures::decoded('register_employer_request');
        $address = BodyReader::object($expected, 'adresseEmploi');
        unset($address['lieuDit']);
        $expected['adresseEmploi'] = $address;
        static::assertEquals($expected, Gateways::jsonBody($request));
        static::assertSame(['tenant-1:register_employer'], $stack->rateLimiter->acquired());
    }

    public function testRegisterEmployerKeepsARejection(): void
    {
        $stack = Gateways::answering(400, Fixtures::load('error_err_metier_iban'));

        try {
            Gateways::of($stack)->registerEmployer(Stacks::tenant(), EmployerSamples::newEmployer());
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $rejected) {
            static::assertSame(Operation::RegisterEmployer, $rejected->operation());
            static::assertSame(400, $rejected->httpStatus());
        }
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function unreadableRegistrations(): iterable
    {
        yield '200 without number' => [200, '{}', 'Unexpected URSSAF response: field "numeroCesu" is missing or empty.'];
        yield '201 invalid JSON' => [201, 'not json', 'Unexpected URSSAF response: body is not valid JSON.'];
    }

    #[DataProvider('unreadableRegistrations')]
    public function testAnUnreadableSuccessIsAnUnknownOutcome(int $status, string $body, string $reason): void
    {
        $stack = Gateways::answering($status, $body);

        try {
            Gateways::of($stack)->registerEmployer(Stacks::tenant(), EmployerSamples::newEmployer());
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $unknown) {
            static::assertSame(Operation::RegisterEmployer, $unknown->operation());
            static::assertSame($status, $unknown->httpStatus());
            static::assertInstanceOf(UnexpectedUrssafResponse::class, $unknown->getPrevious());
            static::assertSame($reason, $unknown->getPrevious()->getMessage());
        }

        $errors = array_values(array_filter(
            $stack->logger->records(),
            static fn(array $record): bool => 'error' === $record['level'],
        ));
        static::assertCount(1, $errors);
        static::assertSame('URSSAF method answered a success that could not be read: outcome unknown.', $errors[0]['message']);
        static::assertSame(
            ['operation' => '010', 'status' => $status, 'reason' => $reason],
            $errors[0]['context'],
        );
    }

    public function testVerifyEmployerWithoutEmployee(): void
    {
        $stack = Gateways::answering(200, Fixtures::load('verify_employer_response_assembled'));

        $status = Gateways::of($stack)->verifyEmployer(
            Stacks::tenant(),
            EmployerVerification::employerOnly(GatewaySamples::employer()),
        );

        static::assertTrue($status->registered());
        static::assertSame(CesuPlusState::Active, $status->cesuPlusState());
        $request = Gateways::apiRequest($stack);
        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/cesu/employeurs/Z0003752390009?dateNaissance=1975-01-30',
            (string) $request->getUri(),
        );
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame('', (string) $request->getBody());
        static::assertSame(['tenant-1:verify_employer'], $stack->rateLimiter->acquired());
    }

    public function testVerifyEmployerWithAnEmployee(): void
    {
        $stack = Gateways::answering(200, Fixtures::load('verify_employer_response_assembled'));

        Gateways::of($stack)->verifyEmployer(
            Stacks::tenant(),
            EmployerVerification::forPair(GatewaySamples::employer(), EmployeeSamples::byCesuNumber()),
        );

        static::assertSame(
            ['dateNaissance' => '1975-01-30', 'numeroCesuSala' => '00000123456789'],
            Gateways::query(Gateways::apiRequest($stack)),
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function unreadableVerifications(): iterable
    {
        yield '200 empty object' => [200, '{}'];
        yield '200 invalid JSON' => [200, 'not json'];
        yield '204 empty body' => [204, ''];
    }

    #[DataProvider('unreadableVerifications')]
    public function testAnUnreadableSuccessOfASafeOperationIsUnexpected(int $status, string $body): void
    {
        $stack = Gateways::answering($status, $body);

        $this->expectException(UnexpectedUrssafResponse::class);

        Gateways::of($stack)->verifyEmployer(
            Stacks::tenant(),
            EmployerVerification::employerOnly(GatewaySamples::employer()),
        );
    }

    public function testASafeOperationLogsNoUnknownOutcome(): void
    {
        $stack = Gateways::answering(200, '{}');

        try {
            Gateways::of($stack)->verifyEmployer(
                Stacks::tenant(),
                EmployerVerification::employerOnly(GatewaySamples::employer()),
            );
            static::fail('An unexpected response was expected.');
        } catch (UnexpectedUrssafResponse) {
            static::assertSame([], array_filter(
                $stack->logger->records(),
                static fn(array $record): bool => 'error' === $record['level'],
            ));
        }
    }
}
