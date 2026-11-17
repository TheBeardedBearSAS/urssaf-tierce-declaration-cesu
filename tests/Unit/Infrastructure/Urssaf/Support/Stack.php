<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support;

use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

/**
 * Wiring of the Urssaf infrastructure on top of a PSR-18 mock client and in-memory fakes.
 *
 * One accessor per collaborator of the stack.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class Stack
{
    public Client $http;
    public InMemoryTokenStore $tokens;
    public RecordingRateLimiter $rateLimiter;
    public SteppingClock $clock;
    public CapturingLogger $logger;
    public StaticCredentialsProvider $credentials;
    public Psr17Factory $factory;
    public SequenceIdGenerator $ids;

    public function __construct(public ApiConfiguration $configuration, int $clockStepMicroseconds)
    {
        $this->http = new Client();
        $this->tokens = new InMemoryTokenStore();
        $this->rateLimiter = new RecordingRateLimiter();
        $this->clock = new SteppingClock($clockStepMicroseconds);
        $this->logger = new CapturingLogger();
        $this->credentials = new StaticCredentialsProvider();
        $this->factory = new Psr17Factory();
        $this->ids = new SequenceIdGenerator();
    }

    /**
     * Fetches and caches a token now, so that a later failure queued on the mock client hits the API call itself.
     */
    public function warmUpToken(): void
    {
        $this->respondToken();
        $this->tokenProvider()->accessToken(
            $this->credentials->forTenant(Stacks::tenant()),
            Operation::VerifyEmployer,
        );
        $this->logger->clear();
    }

    public function tokenProvider(): OAuth2TokenProvider
    {
        return new OAuth2TokenProvider(
            $this->http,
            $this->factory,
            $this->factory,
            $this->tokens,
            $this->clock,
            $this->configuration,
            $this->logger,
        );
    }

    public function jsonClient(): JsonApiClient
    {
        return $this->jsonClientWith($this->rateLimiter);
    }

    public function transport(): HttpTransport
    {
        return new HttpTransport($this->http, $this->factory, $this->factory);
    }

    public function jsonClientWith(RateLimiter $rateLimiter): JsonApiClient
    {
        return new JsonApiClient(
            $this->http,
            $this->factory,
            $this->factory,
            $this->tokenProvider(),
            $rateLimiter,
            $this->ids,
            $this->clock,
            $this->configuration,
            $this->logger,
        );
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return array_values($this->http->getRequests());
    }

    public function lastRequest(): RequestInterface
    {
        $requests = $this->requests();
        $last = array_pop($requests);

        return $last ?? throw new LogicException('No request was sent.');
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(int $status, string $body, array $headers = []): void
    {
        $this->http->addResponse(new Response($status, $headers, $body));
    }

    public function respondToken(): void
    {
        $this->respond(200, Fixtures::load('token_response'));
    }

    public function fail(TransportFailure $exception): void
    {
        $this->http->addException($exception);
    }
}
