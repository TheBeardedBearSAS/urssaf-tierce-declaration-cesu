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
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\Payload;

final class PayloadTest extends TestCase
{
    public function testItDistinguishesListsFromObjects(): void
    {
        static::assertTrue(JsonDecoder::decode('[{"a":1}]')->isList());
        static::assertTrue(JsonDecoder::decode('[]')->isList());
        static::assertFalse(JsonDecoder::decode('{"a":1}')->isList());
    }

    public function testEmptyPayloadHasNoData(): void
    {
        static::assertTrue(Payload::empty()->isEmpty());
        static::assertNull(Payload::empty()->string('a'));
    }

    public function testStringReturnsOnlyStrings(): void
    {
        $payload = JsonDecoder::decode('{"s":"x","i":1,"n":null,"a":[],"b":true}');

        static::assertSame('x', $payload->string('s'));
        static::assertNull($payload->string('i'));
        static::assertNull($payload->string('n'));
        static::assertNull($payload->string('a'));
        static::assertNull($payload->string('b'));
        static::assertNull($payload->string('missing'));
    }

    public function testOptionalStringReadsStringsAndNull(): void
    {
        $payload = JsonDecoder::decode('{"s":"x","e":"","n":null}');

        static::assertSame('x', $payload->optionalString('s'));
        static::assertSame('', $payload->optionalString('e'));
        static::assertNull($payload->optionalString('n'));
        static::assertNull($payload->optionalString('missing'));
    }

    public function testOptionalStringRejectsOtherTypes(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('Unexpected URSSAF response: field "s" is not a string.');

        JsonDecoder::decode('{"s":1}')->optionalString('s');
    }

    public function testRequireStringReturnsANonEmptyString(): void
    {
        static::assertSame('x', JsonDecoder::decode('{"s":"x"}')->requireString('s'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function missingStrings(): iterable
    {
        yield 'absent' => ['{}'];
        yield 'null' => ['{"s":null}'];
        yield 'empty' => ['{"s":""}'];
        yield 'integer' => ['{"s":1}'];
        yield 'boolean' => ['{"s":true}'];
    }

    #[DataProvider('missingStrings')]
    public function testRequireStringRejectsMissingEmptyAndNonStringValues(string $json): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('Unexpected URSSAF response: field "s" is missing or empty.');

        JsonDecoder::decode($json)->requireString('s');
    }

    public function testPayloadRejectsScalarDocuments(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('body is neither a JSON object nor a JSON array');

        Payload::fromDecoded('text');
    }
}
