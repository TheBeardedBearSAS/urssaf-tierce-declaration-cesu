<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Testing;

/**
 * Numbered identifiers of the simulator (1, 2, 3...), formatted with a sprintf format holding one `%d`.
 *
 * Source: docs/testing.md (identifier formats), lot 5 design §4.2, §4.5.
 *
 * @internal
 */
final class SimulatedSequence
{
    private int $last = 0;

    public function __construct(private readonly string $format) {}

    public function next(): string
    {
        ++$this->last;

        return \sprintf($this->format, $this->last);
    }
}
