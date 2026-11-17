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
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;

final class JsonDecoderBlankBodyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function blankBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'line breaks' => ["\r\n\t"];
    }

    #[DataProvider('blankBodies')]
    public function testABlankBodyIsAnEmptyPayload(string $body): void
    {
        static::assertTrue(JsonDecoder::decode($body)->isEmpty());
    }

    public function testEmptyObjectsAndArraysAreEmptyPayloads(): void
    {
        static::assertTrue(JsonDecoder::decode('{}')->isEmpty());
        static::assertTrue(JsonDecoder::decode('[]')->isEmpty());
        static::assertFalse(JsonDecoder::decode('{"a":1}')->isEmpty());
    }
}
