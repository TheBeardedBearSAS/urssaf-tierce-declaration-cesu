<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\MandateResponseMapper;

final class MandateResponseMapperTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [" \n"];
        yield 'empty object' => ['{}'];
        yield 'empty list' => ['[]'];
    }

    #[DataProvider('emptyBodies')]
    #[DoesNotPerformAssertions]
    public function testAnEmptyAnswerIsAccepted(string $body): void
    {
        (new MandateResponseMapper())->assertEmpty(JsonDecoder::decode($body));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonEmptyBodies(): iterable
    {
        yield 'object' => ['{"a":1}'];
        yield 'list' => ['[1]'];
        yield 'object with a null' => ['{"a":null}'];
    }

    #[DataProvider('nonEmptyBodies')]
    public function testAnythingElseIsRefused(string $body): void
    {
        try {
            (new MandateResponseMapper())->assertEmpty(JsonDecoder::decode($body));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame('Unexpected URSSAF response: expected an empty body.', $exception->getMessage());
        }
    }
}
