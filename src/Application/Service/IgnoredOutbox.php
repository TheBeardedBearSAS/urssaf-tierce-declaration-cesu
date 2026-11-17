<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service;

use Override;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Default outbox of the {@see UnitOfWork}: integration events are not recorded.
 */
final readonly class IgnoredOutbox implements Outbox
{
    #[Override]
    public function append(IntegrationEvent ...$events): void {}

    #[Override]
    public function pending(int $limit): array
    {
        return [];
    }

    #[Override]
    public function markDispatched(OutboxEntry $entry, Instant $at): void {}

    #[Override]
    public function markFailed(OutboxEntry $entry, string $error): void {}

    #[Override]
    public function purgeDispatchedBefore(Instant $before): int
    {
        return 0;
    }
}
