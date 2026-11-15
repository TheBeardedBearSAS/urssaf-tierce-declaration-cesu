<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\CesuPlusActivationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class CesuPlusActivationResponseMapperTest extends TestCase
{
    public function testOfficialExample(): void
    {
        static::assertTrue((new CesuPlusActivationResponseMapper())->map(Fixtures::payload('activate_cesu_plus_response'))->isActive());
    }

    public function testAnInactiveAnswer(): void
    {
        static::assertFalse((new CesuPlusActivationResponseMapper())->map(JsonDecoder::decode('{"activationCesu":false}'))->isActive());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusals(): iterable
    {
        yield 'empty body' => [''];
        yield 'missing' => ['{}'];
        yield 'null' => ['{"activationCesu":null}'];
        yield 'not a boolean' => ['{"activationCesu":"true"}'];
        yield 'the AICI key is not the answer' => ['{"activationAici":true}'];
    }

    #[DataProvider('refusals')]
    public function testRefusals(string $body): void
    {
        try {
            (new CesuPlusActivationResponseMapper())->map(JsonDecoder::decode($body));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame('Unexpected URSSAF response: field "activationCesu" is missing or not a boolean.', $exception->getMessage());
        }
    }
}
