<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

final class RecordingRateLimiter implements RateLimiter
{
    /** @var list<string> */
    private array $acquired = [];

    public function acquire(TenantId $tenantId, Operation $operation): void
    {
        $this->acquired[] = $tenantId->value() . ':' . $operation->value;
    }

    /**
     * @return list<string>
     */
    public function acquired(): array
    {
        return $this->acquired;
    }
}
