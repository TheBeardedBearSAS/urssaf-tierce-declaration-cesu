<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\Payload;

/**
 * Successful (2xx) answer of an API call. The body is kept raw and decoded lazily, so that the gateway (not the
 * client) decides what an unreadable 2xx means (ADR 0003).
 *
 * @internal
 */
final readonly class ApiResponse
{
    public function __construct(
        private int $status,
        #[SensitiveParameter]
        private string $body,
        private ?string $requestId,
    ) {}

    /**
     * The 2xx status actually received.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * @throws UnexpectedUrssafResponse when the body is not valid JSON or not a JSON object / array
     */
    public function payload(): Payload
    {
        return JsonDecoder::decode($this->body);
    }

    /**
     * No payload at all (ADR 0002): an empty or blank body.
     */
    public function isEmpty(): bool
    {
        return trim($this->body) === '';
    }

    /**
     * Value of the configured request-id header sent with the (last) attempt, null when none is configured.
     */
    public function requestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * @return array{status: int, body: string, requestId: string|null}
     */
    public function __debugInfo(): array
    {
        return [
            'status' => $this->status,
            'body' => $this->body === '' ? '' : '***',
            'requestId' => $this->requestId,
        ];
    }
}
