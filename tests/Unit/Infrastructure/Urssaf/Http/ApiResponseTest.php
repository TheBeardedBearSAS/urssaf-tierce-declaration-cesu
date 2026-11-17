<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\ApiResponse;

final class ApiResponseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideBodies(): iterable
    {
        yield 'empty' => ['', true];
        yield 'blank' => ["  \n", true];
        yield 'empty object' => ['{}', false];
        yield 'object' => ['{"a":1}', false];
        yield 'garbage' => ['x', false];
    }

    #[DataProvider('provideBodies')]
    public function testIsEmptyOnlyForABlankBody(string $body, bool $expected): void
    {
        static::assertSame($expected, (new ApiResponse(200, $body, null))->isEmpty());
    }

    public function testItExposesStatusAndRequestId(): void
    {
        $response = new ApiResponse(201, '{}', 'request-id-1');

        static::assertSame(201, $response->status());
        static::assertSame('request-id-1', $response->requestId());
        static::assertNull((new ApiResponse(200, '{}', null))->requestId());
    }

    public function testAnEmptyObjectIsAnEmptyPayload(): void
    {
        static::assertTrue((new ApiResponse(200, '{}', null))->payload()->isEmpty());
        static::assertTrue((new ApiResponse(200, '', null))->payload()->isEmpty());
    }

    public function testPayloadIsDecodedFromTheBody(): void
    {
        $payload = (new ApiResponse(200, '{"a":"b"}', null))->payload();

        static::assertFalse($payload->isEmpty());
        static::assertSame('b', $payload->string('a'));
    }

    public function testAnInvalidBodyThrowsOnPayloadOnly(): void
    {
        $response = new ApiResponse(200, 'not json', null);

        static::assertSame(200, $response->status());
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('body is not valid JSON');

        $response->payload();
    }

    public function testDebugInfoHidesTheBody(): void
    {
        static::assertSame(
            ['status' => 200, 'body' => '***', 'requestId' => 'request-id-1'],
            (new ApiResponse(200, '{"secret":"x"}', 'request-id-1'))->__debugInfo(),
        );
        static::assertSame(
            ['status' => 204, 'body' => '', 'requestId' => null],
            (new ApiResponse(204, '', null))->__debugInfo(),
        );
    }
}
