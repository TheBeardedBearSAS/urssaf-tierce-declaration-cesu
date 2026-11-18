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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployerRegistrationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class EmployerRegistrationResponseMapperTest extends TestCase
{
    public function testOfficialExample(): void
    {
        static::assertSame('Z0003752390009', (new EmployerRegistrationResponseMapper())->map(Fixtures::payload('register_employer_response'))->value());
    }

    public function testTheCesuNumberIsTrimmed(): void
    {
        $cesuNumber = (new EmployerRegistrationResponseMapper())->map(JsonDecoder::decode('{"numeroCesu":" Z0003752390009 "}'));

        static::assertSame('Z0003752390009', $cesuNumber->value());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'missing' => ['{}', 'Unexpected URSSAF response: field "numeroCesu" is missing or empty.'];
        yield 'empty body' => ['', 'Unexpected URSSAF response: field "numeroCesu" is missing or empty.'];
        yield 'null' => ['{"numeroCesu":null}', 'Unexpected URSSAF response: field "numeroCesu" is missing or empty.'];
        yield 'not a string' => ['{"numeroCesu":12}', 'Unexpected URSSAF response: field "numeroCesu" is missing or empty.'];
        yield 'invalid number' => ['{"numeroCesu":"Z000"}', 'Unexpected URSSAF response: field "numeroCesu" is not valid.'];
        yield 'blank' => ['{"numeroCesu":"   "}', 'Unexpected URSSAF response: field "numeroCesu" is not valid.'];
    }

    #[DataProvider('refusals')]
    public function testRefusals(string $body, string $message): void
    {
        try {
            (new EmployerRegistrationResponseMapper())->map(JsonDecoder::decode($body));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame($message, $exception->getMessage());
        }
    }

    public function testTheDomainFailureIsKeptAsPrevious(): void
    {
        try {
            (new EmployerRegistrationResponseMapper())->map(JsonDecoder::decode('{"numeroCesu":"Z000"}'));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
            static::assertStringNotContainsString('Z000', $exception->getMessage());
        }
    }
}
