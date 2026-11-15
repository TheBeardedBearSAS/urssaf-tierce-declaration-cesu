<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support;

use PHPUnit\Framework\Assert;

/**
 * Typed reads of the untyped request bodies returned by the mappers.
 */
final class BodyReader
{
    /**
     * @param array<string, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    public static function object(array $body, string $key): array
    {
        return self::asArray($body[$key] ?? null);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function asArray(mixed $value): array
    {
        Assert::assertIsArray($value);

        return $value;
    }
}
