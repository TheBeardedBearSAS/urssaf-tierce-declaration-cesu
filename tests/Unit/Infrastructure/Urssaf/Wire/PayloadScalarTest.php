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

final class PayloadScalarTest extends TestCase
{
    public function testRequireIdentifierReadsStringsAndIntegers(): void
    {
        $payload = JsonDecoder::decode('{"s":"209299542888","i":209299542888,"z":0,"big":12345678901234567890}');

        static::assertSame('209299542888', $payload->requireIdentifier('s'));
        static::assertSame('209299542888', $payload->requireIdentifier('i'));
        static::assertSame('0', $payload->requireIdentifier('z'));
        static::assertSame('12345678901234567890', $payload->requireIdentifier('big'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function missingIdentifiers(): iterable
    {
        yield 'absent' => ['{}'];
        yield 'null' => ['{"s":null}'];
        yield 'empty' => ['{"s":""}'];
        yield 'float' => ['{"s":1.5}'];
        yield 'boolean' => ['{"s":true}'];
        yield 'array' => ['{"s":[]}'];
    }

    #[DataProvider('missingIdentifiers')]
    public function testRequireIdentifierRejectsOtherValues(string $json): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('Unexpected URSSAF response: field "s" is missing or not an identifier.');

        JsonDecoder::decode($json)->requireIdentifier('s');
    }

    public function testOptionalBoolReadsBooleansAndNull(): void
    {
        $payload = JsonDecoder::decode('{"t":true,"f":false,"n":null}');

        static::assertTrue($payload->optionalBool('t'));
        static::assertFalse($payload->optionalBool('f'));
        static::assertNull($payload->optionalBool('n'));
        static::assertNull($payload->optionalBool('missing'));
    }

    public function testOptionalBoolRejectsOtherTypes(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('Unexpected URSSAF response: field "b" is not a boolean.');

        JsonDecoder::decode('{"b":"true"}')->optionalBool('b');
    }

    public function testRequireBoolReadsBooleans(): void
    {
        $payload = JsonDecoder::decode('{"t":true,"f":false}');

        static::assertTrue($payload->requireBool('t'));
        static::assertFalse($payload->requireBool('f'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function missingBooleans(): iterable
    {
        yield 'absent' => ['{}'];
        yield 'null' => ['{"b":null}'];
        yield 'string' => ['{"b":"true"}'];
        yield 'integer' => ['{"b":1}'];
    }

    #[DataProvider('missingBooleans')]
    public function testRequireBoolRejectsOtherValues(string $json): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('Unexpected URSSAF response: field "b" is missing or not a boolean.');

        JsonDecoder::decode($json)->requireBool('b');
    }
}
