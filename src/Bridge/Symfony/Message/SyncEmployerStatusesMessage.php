<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message;

/**
 * Asks to refresh (method 020, employer only) the least recently checked employers of one tenant. Holds an identifier
 * only (no personal data, ADR 0007); safe to retry (a read). Lot 6 design 7.1.
 */
final readonly class SyncEmployerStatusesMessage
{
    public function __construct(public string $tenantId) {}
}
