<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class JsonApiClientRateLimitTest extends TestCase
{
    public function testItAcquiresTheRateLimiterOncePerCallForItsOperation(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{}');
        $stack->respond(200, '{}');
        $client = $stack->jsonClient();
        $credentials = $stack->credentials->forTenant(Stacks::tenant());

        $client->send(Stacks::tenant(), $credentials, ApiRequest::get(Operation::VerifyEmployee, '/p', []));
        $client->send(Stacks::tenant(), $credentials, ApiRequest::post(Operation::DeclareSalary, '/q', [], '{}'));

        static::assertSame(['tenant-1:verify_employee', 'tenant-1:declare_salary'], $stack->rateLimiter->acquired());
    }

    public function testTheTokenCallIsNotCounted(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::VerifyEmployee, '/p', []),
        );

        static::assertCount(2, $stack->requests());
        static::assertSame(['tenant-1:verify_employee'], $stack->rateLimiter->acquired());
    }

    public function testARefusedAcquisitionSendsNothingAndFetchesNoToken(): void
    {
        $stack = Stacks::sandbox();
        $client = $stack->jsonClientWith(new RejectingRateLimiter());

        try {
            $client->send(
                Stacks::tenant(),
                $stack->credentials->forTenant(Stacks::tenant()),
                ApiRequest::post(Operation::RegisterEmployer, '/p', [], '{}'),
            );
            static::fail('Expected the rate limit to be enforced.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(30, $exception->retryAfterSeconds());
            static::assertSame(Operation::RegisterEmployer, $exception->operation());
        }
        static::assertSame([], $stack->requests());
        static::assertSame([], $stack->tokens->keys());
        static::assertSame([], $stack->logger->records());
    }
}
