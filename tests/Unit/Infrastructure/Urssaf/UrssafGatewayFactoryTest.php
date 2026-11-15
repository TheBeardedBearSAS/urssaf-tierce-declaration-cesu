<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafGatewayFactoryTest extends TestCase
{
    private static function mandateCalls(UrssafHttpGateway $gateway, int $count): void
    {
        for ($call = 0; $call < $count; ++$call) {
            $gateway->registerMandate(Stacks::tenant(), GatewaySamples::employer());
        }
    }

    private static function stackWithEmptyAnswers(): Stack
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->http->setDefaultResponse(new Response(200, [], ''));

        return $stack;
    }

    private static function gatewayWith(Stack $stack, GatewayOptions $options): UrssafHttpGateway
    {
        return UrssafGatewayFactory::createWith(
            $stack->transport(),
            $stack->credentials,
            $stack->tokens,
            $stack->configuration,
            $options,
        );
    }

    public function testCreateWorksEndToEndWithTheDefaults(): void
    {
        $stack = self::stackWithEmptyAnswers();
        $gateway = UrssafGatewayFactory::create(
            $stack->transport(),
            $stack->credentials,
            new InMemoryTokenStore(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        );

        self::mandateCalls($gateway, 3);

        $requests = $stack->requests();
        static::assertCount(4, $requests);
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', (string) $requests[0]->getUri());
        $headers = array_keys($requests[1]->getHeaders());
        sort($headers);
        static::assertSame(['Accept', 'Authorization', 'Host', 'User-Agent'], $headers);
    }

    public function testTheDefaultRateLimiterAllowsTheQuotaThenRefuses(): void
    {
        $stack = self::stackWithEmptyAnswers();
        $gateway = UrssafGatewayFactory::create(
            $stack->transport(),
            $stack->credentials,
            new InMemoryTokenStore(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        );

        self::mandateCalls($gateway, 2500);
        $sent = \count($stack->requests());

        try {
            self::mandateCalls($gateway, 1);
            static::fail('The 2 501st call of the hour must be refused.');
        } catch (RateLimitExceeded $exceeded) {
            static::assertSame(Operation::RegisterMandate, $exceeded->operation());
        }

        static::assertSame(2501, $sent);
        static::assertCount($sent, $stack->requests());
    }

    public function testTheDefaultRateLimiterUsesTheGivenClock(): void
    {
        $stack = self::stackWithEmptyAnswers();
        $gateway = self::gatewayWith($stack, GatewayOptions::defaults()->withClock($stack->clock));
        self::mandateCalls($gateway, 2500);

        $stack->clock->advanceSeconds(3600);
        $stack->respondToken();

        self::mandateCalls($gateway, 1);
        static::assertCount(2503, $stack->requests());
    }

    public function testGivenOptionsAreUsed(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, 'x-correlation-id');
        $stack = Stacks::stepping(250_000);
        $stack->respondToken();
        $stack->respond(200, '');
        $gateway = UrssafGatewayFactory::createWith(
            $stack->transport(),
            $stack->credentials,
            $stack->tokens,
            $configuration,
            GatewayOptions::defaults()
                ->withRateLimiter($stack->rateLimiter)
                ->withIdGenerator($stack->ids)
                ->withClock($stack->clock)
                ->withLogger($stack->logger),
        );

        $gateway->registerMandate(Stacks::tenant(), GatewaySamples::employer());

        static::assertSame(['tenant-1:register_mandate'], $stack->rateLimiter->acquired());
        static::assertSame('request-id-1', $stack->lastRequest()->getHeaderLine('x-correlation-id'));
        $records = $stack->logger->records();
        static::assertCount(2, $records);
        static::assertSame(250, $records[1]['context']['duration_ms']);
    }

    public function testANoopRateLimiterNeverThrottles(): void
    {
        $stack = self::stackWithEmptyAnswers();
        $gateway = self::gatewayWith($stack, GatewayOptions::defaults()->withRateLimiter(new NoopRateLimiter()));

        self::mandateCalls($gateway, 2501);

        static::assertCount(2502, $stack->requests());
    }

    public function testTheDefaultRequestIdHeaderIsAbsent(): void
    {
        $stack = self::stackWithEmptyAnswers();

        self::mandateCalls(self::gatewayWith($stack, GatewayOptions::defaults()), 1);

        static::assertFalse($stack->lastRequest()->hasHeader('x-correlation-id'));
        static::assertFalse($stack->lastRequest()->hasHeader('x-request-id'));
    }
}
