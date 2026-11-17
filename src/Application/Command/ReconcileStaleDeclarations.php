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
 * Moves the declarations left submitting for too long to outcome_unknown (every tenant, ADR 0003).
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class ReconcileStaleDeclarations
{
    public function __construct(public ?int $limit) {}
}
