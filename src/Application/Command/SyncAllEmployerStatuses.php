<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

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
