<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\AiciActivationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class AiciActivationResponseMapperTest extends TestCase
{
    public function testOfficialExample(): void
    {
        static::assertTrue((new AiciActivationResponseMapper())->map(Fixtures::payload('activate_aici_response'))->isActive());
    }

    public function testAnInactiveAnswer(): void
    {
        static::assertFalse((new AiciActivationResponseMapper())->map(JsonDecoder::decode('{"activationAici":false}'))->isActive());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusals(): iterable
    {
        yield 'empty body' => [''];
        yield 'missing' => ['{}'];
        yield 'null' => ['{"activationAici":null}'];
        yield 'not a boolean' => ['{"activationAici":1}'];
        yield 'the Cesu+ key is not the answer' => ['{"activationCesu":true}'];
    }

    #[DataProvider('refusals')]
    public function testRefusals(string $body): void
    {
        try {
            (new AiciActivationResponseMapper())->map(JsonDecoder::decode($body));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame('Unexpected URSSAF response: field "activationAici" is missing or not a boolean.', $exception->getMessage());
        }
    }
}
