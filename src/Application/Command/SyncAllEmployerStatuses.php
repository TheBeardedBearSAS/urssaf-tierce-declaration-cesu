<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

/**
 * Runs SyncEmployerStatuses for every tenant having tracked employers.
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class SyncAllEmployerStatuses
{
    public function __construct(public ?int $limitPerTenant) {}
}
