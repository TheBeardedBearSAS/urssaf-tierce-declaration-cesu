<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Contract;

use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafEndpoints;

/**
 * PSR-18 decorator of the contract suite: optionally sends the token request to another URL (open point 5.14),
 * records the shape of every exchange (see Exchange) and feeds the passive observations of the report (429, headers).
 * Exceptions of the inner client are rethrown unchanged.
 */
final class SandboxHttpClient implements ClientInterface
{
    /** @var list<Exchange> */
    private array $exchanges = [];

    /** @var list<string> */
    private array $headerNames = [];

    private bool $retryAfterSeen = false;

    private readonly ?string $tokenPath;

    public function __construct(
        private readonly ClientInterface $inner,
        private readonly ?string $tokenUri,
    ) {
        $path = null === $tokenUri ? null : parse_url($tokenUri, \PHP_URL_PATH);
        $this->tokenPath = \is_string($path) ? $path : null;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (null !== $this->tokenUri && UrssafEndpoints::TOKEN_PATH === $request->getUri()->getPath()) {
            $request = $request->withUri(new Uri($this->tokenUri));
        }

        return $this->dispatch($request)[0];
    }

    /**
     * Sends the request exactly as given (no token rewrite) and returns what was recorded for it.
     */
    public function probe(RequestInterface $request): Exchange
    {
        return $this->dispatch($request)[1];
    }

    /**
     * @return list<Exchange>
     */
    public function exchanges(): array
    {
        return $this->exchanges;
    }

    /**
     * Whether the exchange is a token request (default token path or the configured token URI's path), not an API call.
     */
    public function isTokenExchange(Exchange $exchange): bool
    {
        return str_ends_with($exchange->pathTemplate, UrssafEndpoints::TOKEN_PATH)
            || null !== $this->tokenPath && str_ends_with($exchange->pathTemplate, $this->tokenPath);
    }

    public function mark(): int
    {
        return \count($this->exchanges);
    }

    /**
     * @return list<Exchange> the exchanges recorded after the mark
     */
    public function since(int $mark): array
    {
        return \array_slice($this->exchanges, $mark);
    }

    public function last(): ?Exchange
    {
        $last = end($this->exchanges);

        return false === $last ? null : $last;
    }

    /**
     * @return array{0: ResponseInterface, 1: Exchange}
     */
    private function dispatch(RequestInterface $request): array
    {
        $response = $this->inner->sendRequest($request);
        $exchange = Exchange::from($request, $response);
        $this->exchanges[] = $exchange;
        $this->observe($exchange);

        return [$response, $exchange];
    }

    private function observe(Exchange $exchange): void
    {
        if ($this->isTokenExchange($exchange)) {
            return;
        }

        $this->retryAfterSeen = $this->retryAfterSeen || null !== $exchange->retryAfter;

        $this->headerNames = array_values(array_unique([...$this->headerNames, ...$exchange->headerNames]));
        sort($this->headerNames);
        SandboxReport::record(
            '5.15-headers',
            SandboxReport::OBSERVED,
            ['headerNames' => $this->headerNames, 'retryAfterSeen' => $this->retryAfterSeen],
            'http-client',
        );

        if (429 === $exchange->status) {
            SandboxReport::record(
                '5.15-429',
                SandboxReport::OBSERVED,
                [
                    'retryAfter' => $exchange->retryAfter ?? 'absent',
                    'bodyShape' => $exchange->bodyShape,
                    'bodyKeys' => $exchange->bodyKeys,
                    'codes' => $exchange->codes(),
                ],
                'http-client',
            );
        }
    }
}
