<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

final readonly class PurgeOutboxHandler
{
    public function __construct(private Outbox $outbox, private Clock $clock) {}

    /**
     * @return int the number of deleted entries
     */
    public function __invoke(PurgeOutbox $command): int
    {
        return $this->outbox->purgeDispatchedBefore(Instant::fromDateTime($this->clock->now()->sub($command->retention)));
    }
}
