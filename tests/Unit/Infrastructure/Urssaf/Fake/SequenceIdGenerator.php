<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;

final class SequenceIdGenerator implements IdGenerator
{
    private int $counter = 0;

    /**
     * @return non-empty-string
     */
    public function generate(): string
    {
        ++$this->counter;

        return 'request-id-' . $this->counter;
    }
}
