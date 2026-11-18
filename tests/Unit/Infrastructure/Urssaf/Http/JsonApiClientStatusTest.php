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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\TransportError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

/**
 * Status mapping of the client on a safe operation (020, ADR 0002; "safe" column of the lot 3 design section 8).
 *
 * One method per status family.
 *
 * @mago-expect lint:too-many-methods
 */
final class JsonApiClientStatusTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    private function call(int $status, string $body, array $headers = []): ApiResponse
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, $body, $headers);

        return $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::VerifyEmployer, '/p', []),
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideSuccessStatuses(): iterable
    {
        yield '200' => [200];
        yield '201' => [201];
        yield '299' => [299];
    }

    #[DataProvider('provideSuccessStatuses')]
    public function testTwoHundredStatusesKeepTheStatusAndTheRawBody(int $status): void
    {
        $response = $this->call($status, '{"k":"v"}');

        static::assertSame($status, $response->status());
        static::assertSame('v', $response->payload()->string('k'));
        static::assertNull($response->requestId());
    }

    public function testASuccessIsNotDecodedByTheClient(): void
    {
        $response = $this->call(200, 'not json');

        static::assertSame(200, $response->status());
        static::assertFalse($response->isEmpty());
    }

    public function testA400IsClassifiedByItsErrorCode(): void
    {
        try {
            $this->call(400, Fixtures::load('error_param_invalide'));
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertSame(400, $exception->httpStatus());
            static::assertSame(Operation::VerifyEmployer, $exception->operation());
            static::assertSame(ErrorKind::InvalidParameter, $exception->kind());
            static::assertSame(['PARAM_INVALIDE'], $exception->errors()->codes());
        }
    }

    public function testA400WithAnUnreadableBodyIsARejectionWithoutErrors(): void
    {
        try {
            $this->call(400, '<html>Bad Request</html>');
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertTrue($exception->errors()->isEmpty());
            static::assertSame(ErrorKind::Unknown, $exception->kind());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideAuthenticationStatuses(): iterable
    {
        yield 'unauthorized after the replay' => [401];
        yield 'forbidden' => [403];
        yield 'security gateway' => [512];
    }

    #[DataProvider('provideAuthenticationStatuses')]
    public function testSecurityStatusesFailAuthentication(int $status): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, '');
        $stack->respondToken();
        $stack->respond($status, '');

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                $stack->credentials->forTenant(Stacks::tenant()),
                ApiRequest::get(Operation::VerifyEmployer, '/p', []),
            );
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame($status, $exception->httpStatus());
        }
    }

    /**
     * @return iterable<string, array{int, array<string, string>, int|null}>
     */
    public static function provideRetryableStatuses(): iterable
    {
        yield 'quota with seconds' => [429, ['Retry-After' => '12'], 12];
        yield 'quota without header' => [429, [], null];
        yield 'quota with http date' => [429, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], null];
        yield 'technical error' => [500, [], null];
        yield 'bad gateway' => [502, ['Retry-After' => '1'], 1];
        yield 'unavailable with seconds' => [503, ['Retry-After' => '3'], 3];
        yield 'gateway timeout' => [504, [], null];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideRetryableStatuses')]
    public function testQuotaAndListedServerErrorsAreRetryable(int $status, array $headers, ?int $retryAfter): void
    {
        try {
            $this->call($status, Fixtures::load('error_technical'), $headers);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertSame($status, $exception->httpStatus());
            static::assertSame($retryAfter, $exception->retryAfterSeconds());
            static::assertSame($status === 429, $exception->isQuotaExceeded());
            static::assertSame(Operation::VerifyEmployer, $exception->operation());
            static::assertSame($status !== 429, $exception->mayHaveReachedUrssaf());
            static::assertSame(['ERR_TECHNIQUE'], $exception->errors()->codes());
        }
    }

    public function testAnUnreadableErrorBodyGivesNoErrors(): void
    {
        try {
            $this->call(503, '<html>');
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertTrue($exception->errors()->isEmpty());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideUnexpectedStatuses(): iterable
    {
        yield 'informational' => [199];
        yield 'multiple choices' => [300];
        yield 'redirect' => [302];
        yield 'payment required' => [402];
        yield 'not found' => [404];
        yield 'method not allowed' => [405];
        yield 'conflict' => [409];
        yield 'teapot' => [418];
        yield 'unprocessable' => [422];
        yield 'not implemented' => [501];
        yield 'http version' => [505];
        yield 'last 5xx' => [599];
    }

    #[DataProvider('provideUnexpectedStatuses')]
    public function testAnyOtherStatusIsUnexpectedOnASafeOperation(int $status): void
    {
        try {
            $this->call($status, '{}');
            static::fail('Expected an unexpected-response exception.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame($status, $exception->getCode());
            static::assertStringContainsString(\sprintf('HTTP %d', $status), $exception->getMessage());
        }
    }

    public function testNoStatusOfASafeOperationBecomesAnUnknownOutcome(): void
    {
        foreach ([500, 501, 503, 512, 599] as $status) {
            try {
                $this->call($status, '{}');
            } catch (OutcomeUnknown) {
                static::fail(\sprintf('HTTP %d must not be an unknown outcome on a safe operation.', $status));
            } catch (UrssafUnavailable|UnexpectedUrssafResponse|UrssafAuthenticationFailed) {
                static::addToAssertionCount(1);
            }
        }
    }

    public function testATransportErrorIsUnavailableWithASanitisedCause(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout for https://h/salaries?nir=FICTIVE&nom=FICTIF'));

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                $stack->credentials->forTenant(Stacks::tenant()),
                ApiRequest::get(Operation::VerifyEmployee, '/p', []),
            );
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertNull($exception->httpStatus());
            static::assertSame(Operation::VerifyEmployee, $exception->operation());
            static::assertTrue($exception->mayHaveReachedUrssaf());
            $previous = $exception->getPrevious();
            static::assertInstanceOf(TransportError::class, $previous);
            static::assertStringNotContainsString('FICTIVE', $exception->getMessage());
            static::assertStringNotContainsString('FICTIVE', $previous->getMessage());
        }
    }
}
