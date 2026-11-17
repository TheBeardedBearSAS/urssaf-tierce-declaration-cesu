<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpHeader;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorClassifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorPayloadParser;

/**
 * Authenticated JSON transport over PSR-18 (ADR 0002, ADR 0003, ADR 0011). Build the gateway with
 * {@see \TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafGatewayFactory}.
 *
 * Per attempt: rate limiter, bearer token, optional request-id header (a new id per attempt) and optional
 * application-id header. A 401 invalidates the cached token and the call is replayed exactly once, for every
 * operation (a 401 proves the call was not processed). Statuses are then mapped to the gateway exceptions; for the
 * operations that are not safe to retry (010, 050, 060, 070) a transport error or any 5xx other than 512 becomes an
 * {@see OutcomeUnknown} (ADR 0003). Neither the token, the URI, the query nor request / response bodies are logged
 * or put in an exception message (ADR 0007).
 *
 * The status table of the lot 3 design (section 8) is one decision per branch, hence the complexity.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class JsonApiClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private OAuth2TokenProvider $tokenProvider,
        private RateLimiter $rateLimiter,
        private IdGenerator $idGenerator,
        private Clock $clock,
        private ApiConfiguration $configuration,
        private LoggerInterface $logger = new NullLogger(),
        private ErrorPayloadParser $errorParser = new ErrorPayloadParser(),
    ) {}

    /**
     * Sends one call (plus one replay after a 401) and returns the raw 2xx answer; the caller decodes it.
     *
     * @throws RateLimitExceeded          local limiter (before any HTTP call)
     * @throws UrssafRequestRejected      HTTP 400, classified by {@see ErrorClassifier::rejection()}
     * @throws UrssafAuthenticationFailed 401 after the replay, 403, 512; token 400/401/403
     * @throws UrssafUnavailable          429; 500/502/503/504 and transport errors of safe operations; token failures
     * @throws OutcomeUnknown             unsafe operations only: transport error, any 5xx other than 512
     * @throws UnexpectedUrssafResponse   any other status; token response unreadable
     */
    public function send(TenantId $tenantId, Credentials $credentials, ApiRequest $request): ApiResponse
    {
        [$response, $requestId] = $this->attempt($tenantId, $credentials, $request);

        if ($response->getStatusCode() === 401) {
            $this->tokenProvider->invalidate($credentials);
            $this->logger->info('URSSAF call answered 401: token invalidated, replaying once.', [
                'operation' => $request->operation()->methodNumber(),
                'path' => $request->path(),
            ]);
            [$response, $requestId] = $this->attempt($tenantId, $credentials, $request);
        }

        return $this->interpret($request->operation(), $response, $requestId);
    }

    /**
     * @return array{ResponseInterface, string|null} the response and the request id sent (null when not configured)
     */
    private function attempt(TenantId $tenantId, Credentials $credentials, ApiRequest $request): array
    {
        $operation = $request->operation();
        $this->rateLimiter->acquire($tenantId, $operation);

        $token = $this->tokenProvider->accessToken($credentials, $operation);
        $requestIdHeaderName = $this->configuration->requestIdHeaderName();
        $requestIdHeader = $requestIdHeaderName === null ? null : [$requestIdHeaderName, $this->idGenerator->generate()];
        $requestId = $requestIdHeader[1] ?? null;
        $httpRequest = $this->buildRequest($request, $token, $requestIdHeader);
        $context = ['operation' => $operation->methodNumber(), 'method' => $request->method(), 'path' => $request->path()];
        $startedAt = $this->clock->now();

        try {
            $response = $this->httpClient->sendRequest($httpRequest);
        } catch (ClientExceptionInterface $exception) {
            $this->logger->warning('URSSAF call failed: transport error.', $this->withRequestId($context + [
                'duration_ms' => Duration::millisecondsSince($startedAt, $this->clock->now()),
                'exception' => $exception::class,
            ], $requestId));

            $unavailable = UrssafUnavailable::dueToTransportError($operation, TransportError::fromClientException($exception));

            throw $operation->isSafeToRetry() ? $unavailable : OutcomeUnknown::fromUnavailable($unavailable);
        }

        $this->logger->info('URSSAF call.', $this->withRequestId($context + [
            'status' => $response->getStatusCode(),
            'duration_ms' => Duration::millisecondsSince($startedAt, $this->clock->now()),
        ], $requestId));

        return [$response, $requestId];
    }

    /**
     * @param array<string, int|string> $context
     *
     * @return array<string, int|string>
     */
    private function withRequestId(array $context, ?string $requestId): array
    {
        return $requestId === null ? $context : $context + ['request_id' => $requestId];
    }

    /**
     * @param array{string, string}|null $requestIdHeader the configured request-id header name and its generated value
     */
    private function buildRequest(
        ApiRequest $request,
        #[SensitiveParameter]
        string $token,
        ?array $requestIdHeader,
    ): RequestInterface {
        $uri = $this->configuration->apiUri($request->path());
        if ($request->query() !== []) {
            $uri .= '?' . http_build_query($request->query(), '', '&', PHP_QUERY_RFC3986);
        }

        $httpRequest = $this->requestFactory
            ->createRequest($request->method(), $uri)
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->configuration->userAgent());

        if ($requestIdHeader !== null) {
            $httpRequest = $httpRequest->withHeader($requestIdHeader[0], $requestIdHeader[1]);
        }

        $applicationId = $this->configuration->applicationIdHeader();
        if ($applicationId instanceof HttpHeader) {
            $httpRequest = $httpRequest->withHeader($applicationId->name(), $applicationId->value());
        }

        $body = $request->jsonBody();
        if ($body !== null) {
            $httpRequest = $httpRequest
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($body));
        }

        return $httpRequest;
    }

    private function interpret(Operation $operation, ResponseInterface $response, ?string $requestId): ApiResponse
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status >= 200 && $status < 300) {
            return new ApiResponse($status, $body, $requestId);
        }

        if ($status === 400) {
            throw ErrorClassifier::rejection($operation, $status, $this->errorParser->parseBody($body));
        }

        if (\in_array($status, [401, 403, 512], true)) {
            throw UrssafAuthenticationFailed::withStatus($status);
        }

        if (!$operation->isSafeToRetry() && $status >= 500) {
            throw OutcomeUnknown::fromUnavailable(UrssafUnavailable::withStatus(
                $operation,
                $status,
                RetryAfter::seconds($response),
                $this->errorParser->parseBody($body),
            ));
        }

        if (\in_array($status, [429, 500, 502, 503, 504], true)) {
            throw UrssafUnavailable::withStatus(
                $operation,
                $status,
                RetryAfter::seconds($response),
                $this->errorParser->parseBody($body),
            );
        }

        throw UnexpectedUrssafResponse::unexpectedStatus($operation, $status);
    }
}
