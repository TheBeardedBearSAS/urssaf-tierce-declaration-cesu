<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port;

use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;

/**
 * An integration event waiting in the outbox.
 */
final readonly class OutboxEntry
{
    /**
     * @param int $sequence position in the outbox (recording order)
     * @param int $attempts failed dispatch attempts so far
     */
    public function __construct(public int $sequence, public IntegrationEvent $event, public int $attempts) {}
}
