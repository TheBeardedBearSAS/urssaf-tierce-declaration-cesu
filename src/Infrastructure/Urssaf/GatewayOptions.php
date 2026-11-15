<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf;

use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;

/**
 * Optional collaborators of the gateway (see {@see UrssafGatewayFactory}), immutable; each one not given keeps its
 * default:
 * - rate limiter: an in-memory sliding window of 2 500 calls per hour and tenant (ADR 0011), within the current
 *   process; give a `NoopRateLimiter` to leave the URSSAF quota alone (HTTP 429), or a shared limiter across workers;
 * - id generator: random UUIDs (used only when a request-id header is configured);
 * - clock: the system clock (UTC);
 * - logger: no logging.
 *
 *     GatewayOptions::defaults()->withLogger($logger)->withRateLimiter($sharedRateLimiter)
 */
final readonly class GatewayOptions
{
    private function __construct(
        private ?RateLimiter $rateLimiter,
        private ?IdGenerator $idGenerator,
        private ?Clock $clock,
        private ?LoggerInterface $logger,
    ) {}

    public static function defaults(): self
    {
        return new self(null, null, null, null);
    }

    public function withRateLimiter(RateLimiter $rateLimiter): self
    {
        return new self($rateLimiter, $this->idGenerator, $this->clock, $this->logger);
    }

    public function withIdGenerator(IdGenerator $idGenerator): self
    {
        return new self($this->rateLimiter, $idGenerator, $this->clock, $this->logger);
    }

    public function withClock(Clock $clock): self
    {
        return new self($this->rateLimiter, $this->idGenerator, $clock, $this->logger);
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return new self($this->rateLimiter, $this->idGenerator, $this->clock, $logger);
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function rateLimiter(): ?RateLimiter
    {
        return $this->rateLimiter;
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function idGenerator(): ?IdGenerator
    {
        return $this->idGenerator;
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function clock(): ?Clock
    {
        return $this->clock;
    }

    /**
     * Null when not given (the factory default applies).
     */
    public function logger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
