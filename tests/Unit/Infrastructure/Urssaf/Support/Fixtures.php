<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support;

use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\Payload;

/**
 * Wire fixtures of tests/Fixtures/wire (api-reference examples), read by their name without extension.
 */
final class Fixtures
{
    private function __construct() {}

    public static function load(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../../../Fixtures/wire/' . $name . '.json');
        Assert::assertIsString($content, 'Missing fixture ' . $name);

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decoded(string $name): array
    {
        return self::asMap(json_decode(self::load($name), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private static function asMap(mixed $decoded): array
    {
        Assert::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public static function payload(string $name): Payload
    {
        return JsonDecoder::decode(self::load($name));
    }
}
