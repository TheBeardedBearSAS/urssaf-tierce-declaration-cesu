<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port;

/**
 * Generates unique identifiers (UUID v4 recommended) for local records and integration events.
 */
interface IdGenerator
{
    /**
     * @return non-empty-string
     */
    public function generate(): string;
}
