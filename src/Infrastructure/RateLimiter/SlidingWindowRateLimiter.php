<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Non-blocking in-memory limiter of the global URSSAF quota (ADR 0011): at most $limit calls per tenant, all operations
 * together, in any sliding window of $windowSeconds (default 2 500 per 3 600 s, api-reference §1.1 (PDF §2.5)). Per process. Token
 * calls are not counted (the gateway never acquires for them). Call instants are kept at second precision; when the
 * limit is reached the retry delay is the time left until the oldest call leaves the window.
 */
final class SlidingWindowRateLimiter implements RateLimiter
{
    public const DEFAULT_LIMIT = 2500;
    public const DEFAULT_WINDOW_SECONDS = 3600;

    /** @var array<string, array<int, int>> call instants (UNIX timestamps) per tenant identifier */
    private array $calls = [];

    /**
     * @throws InvalidValue "rate_limit.not_positive" / "rate_limit_window.not_positive"
     */
    public function __construct(
        private readonly Clock $clock,
        private readonly int $limit = self::DEFAULT_LIMIT,
        private readonly int $windowSeconds = self::DEFAULT_WINDOW_SECONDS,
    ) {
        if ($limit < 1) {
            throw InvalidValue::notPositive('rate_limit');
        }

        if ($windowSeconds < 1) {
            throw InvalidValue::notPositive('rate_limit_window');
        }
    }

    /**
     * @throws RateLimitExceeded
     */
    public function acquire(TenantId $tenantId, Operation $operation): void
    {
        $now = $this->clock->now()->getTimestamp();
        $windowStart = $now - $this->windowSeconds;
        $recent = array_filter(
            $this->calls[$tenantId->value()] ?? [],
            static fn(int $instant): bool => $instant > $windowStart,
        );

        if (\count($recent) >= $this->limit) {
            throw RateLimitExceeded::forOperation($operation, min([$now, ...$recent]) + $this->windowSeconds - $now);
        }

        $recent[] = $now;
        $this->calls[$tenantId->value()] = $recent;
    }
}
