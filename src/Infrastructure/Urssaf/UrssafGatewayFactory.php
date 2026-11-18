<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf;

use Psr\Log\NullLogger;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\OAuth2TokenProvider;

/**
 * Public entry point to build the URSSAF HTTP gateway outside a framework bridge (ADR 0000, ADR 0002):
 *
 *     $gateway = UrssafGatewayFactory::createWith(
 *         new HttpTransport($psr18Client, $psr17RequestFactory, $psr17StreamFactory),
 *         $credentialsProvider,
 *         $tokenStore,
 *         ApiConfiguration::forEnvironment(Environment::Production),
 *         GatewayOptions::defaults()->withLogger($logger),
 *     );
 *
 * {@see self::create()} takes the same arguments without the options. The default rate limiter is a
 * {@see SlidingWindowRateLimiter} (ADR 0011).
 *
 * The OAuth2 token provider and the JSON client it wires are internal.
 */
final class UrssafGatewayFactory
{
    /**
     * Gateway with the default options ({@see GatewayOptions::defaults()}).
     */
    public static function create(
        HttpTransport $transport,
        CredentialsProvider $credentials,
        TokenStore $tokens,
        ApiConfiguration $configuration,
    ): UrssafHttpGateway {
        return self::createWith($transport, $credentials, $tokens, $configuration, GatewayOptions::defaults());
    }

    public static function createWith(
        HttpTransport $transport,
        CredentialsProvider $credentials,
        TokenStore $tokens,
        ApiConfiguration $configuration,
        GatewayOptions $options,
    ): UrssafHttpGateway {
        $clock = $options->clock() ?? new UtcSystemClock();
        $logger = $options->logger() ?? new NullLogger();
        $tokenProvider = new OAuth2TokenProvider(
            $transport->client(),
            $transport->requestFactory(),
            $transport->streamFactory(),
            $tokens,
            $clock,
            $configuration,
            $logger,
        );
        $client = new JsonApiClient(
            $transport->client(),
            $transport->requestFactory(),
            $transport->streamFactory(),
            $tokenProvider,
            $options->rateLimiter() ?? new SlidingWindowRateLimiter($clock),
            $options->idGenerator() ?? new RandomUuidGenerator(new SecureRandomBytes()),
            $clock,
            $configuration,
            $logger,
        );

        return new UrssafHttpGateway($credentials, $client, $configuration->agreementEncoding(), $logger);
    }
}
