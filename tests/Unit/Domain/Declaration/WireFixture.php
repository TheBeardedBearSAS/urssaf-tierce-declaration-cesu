<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Assert;

/**
 * Typed access to the official wire examples (tests/Fixtures/wire), to map them to drafts by hand in the tests.
 */
final class WireFixture
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $name): array
    {
        $contents = file_get_contents(__DIR__ . '/../../../Fixtures/wire/' . $name);
        Assert::assertIsString($contents);

        return self::map(json_decode($contents, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        Assert::assertIsArray($value);

        return array_combine(array_map(strval(...), array_keys($value)), $value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function maps(mixed $value): array
    {
        Assert::assertIsArray($value);

        return array_values(array_map(self::map(...), $value));
    }

    public static function string(mixed $value): string
    {
        Assert::assertIsString($value);

        return $value;
    }

    public static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }

    /**
     * An integer or decimal number as the string a draft expects ("12.5", "10").
     */
    public static function number(mixed $value): string
    {
        Assert::assertTrue(\is_int($value) || \is_float($value));

        return (string) $value;
    }

    public static function int(mixed $value): int
    {
        Assert::assertIsInt($value);

        return $value;
    }

    public static function bool(mixed $value): bool
    {
        Assert::assertIsBool($value);

        return $value;
    }
}
