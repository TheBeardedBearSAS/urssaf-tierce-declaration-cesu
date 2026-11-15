<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support;

use LogicException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafHttpGateway;

/**
 * Builds the public gateway on top of a {@see Stack} (PSR-18 mock client and in-memory fakes).
 */
final class Gateways
{
    private function __construct() {}

    public static function of(Stack $stack): UrssafHttpGateway
    {
        return self::withCredentials($stack, $stack->credentials);
    }

    public static function withCredentials(Stack $stack, CredentialsProvider $credentials): UrssafHttpGateway
    {
        return UrssafGatewayFactory::createWith(
            $stack->transport(),
            $credentials,
            $stack->tokens,
            $stack->configuration,
            GatewayOptions::defaults()
                ->withRateLimiter($stack->rateLimiter)
                ->withIdGenerator($stack->ids)
                ->withClock($stack->clock)
                ->withLogger($stack->logger),
        );
    }

    /**
     * A sandbox stack that answers the token call, then the API call with the given status and body.
     */
    public static function answering(int $status, string $body): Stack
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond($status, $body);

        return $stack;
    }

    /**
     * The API call (the first request is the token call).
     */
    public static function apiRequest(Stack $stack): RequestInterface
    {
        return $stack->requests()[1] ?? throw new LogicException('The API call was not sent.');
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function query(RequestInterface $request): array
    {
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        return $query;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function jsonBody(RequestInterface $request): array
    {
        return self::asArray(json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function asArray(mixed $decoded): array
    {
        Assert::assertIsArray($decoded);

        return $decoded;
    }
}
