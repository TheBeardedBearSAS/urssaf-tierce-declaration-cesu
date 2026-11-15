<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use Throwable;

final class JsonApiClientRetryTest extends TestCase
{
    private const RENEWAL_RESPONSE = '{"access_token":"second-token","expires_in":3600}';

    public function testA401InvalidatesTheTokenAndReplaysTheCallExactlyOnce(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(Environment::Sandbox, null, 'x-request-id'));
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, self::RENEWAL_RESPONSE);
        $stack->respond(200, '{"ok":"retried"}');

        $response = $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::VerifyEmployer, '/p', []),
        );

        static::assertSame('retried', $response->payload()->string('ok'));
        $requests = $stack->requests();
        static::assertCount(4, $requests);
        static::assertSame('Bearer fictitious-access-token-0001', $requests[1]->getHeaderLine('Authorization'));
        static::assertSame('Bearer second-token', $requests[3]->getHeaderLine('Authorization'));
        static::assertSame('request-id-1', $requests[1]->getHeaderLine('x-request-id'));
        static::assertSame('request-id-2', $requests[3]->getHeaderLine('x-request-id'));
        static::assertSame('request-id-2', $response->requestId());
    }

    public function testTheReplayAcquiresTheRateLimiterAgain(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, self::RENEWAL_RESPONSE);
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::VerifyEmployer, '/p', []),
        );

        static::assertSame(['tenant-1:verify_employer', 'tenant-1:verify_employer'], $stack->rateLimiter->acquired());
    }

    public function testAnUnsafeOperationIsReplayedAfterA401(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, self::RENEWAL_RESPONSE);
        $stack->respond(201, '{"ok":"created"}');

        $response = $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::post(Operation::DeclareSalary, '/p', [], '{"k":1}'),
        );

        static::assertSame(201, $response->status());
        static::assertCount(4, $stack->requests());
        static::assertSame('{"k":1}', (string) $stack->lastRequest()->getBody());
    }

    public function testASecond401FailsAuthenticationWithoutAThirdAttempt(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, self::RENEWAL_RESPONSE);
        $stack->respond(401, '');

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                $stack->credentials->forTenant(Stacks::tenant()),
                ApiRequest::get(Operation::VerifyEmployer, '/p', []),
            );
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame(401, $exception->httpStatus());
        }
        static::assertCount(4, $stack->requests());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNeverReplayedStatuses(): iterable
    {
        yield 'forbidden' => [403];
        yield 'security gateway' => [512];
        yield 'bad request' => [400];
        yield 'server error' => [500];
    }

    #[DataProvider('provideNeverReplayedStatuses')]
    public function testOtherStatusesAreNeverReplayed(int $status): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond($status, '');

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                $stack->credentials->forTenant(Stacks::tenant()),
                ApiRequest::get(Operation::VerifyEmployer, '/p', []),
            );
            static::fail('Expected a failure.');
        } catch (Throwable) {
            static::assertCount(2, $stack->requests());
            static::assertCount(1, $stack->tokens->keys());
            static::assertCount(1, $stack->rateLimiter->acquired());
        }
    }
}
