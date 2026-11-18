<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\WorkflowStatistics;

final readonly class FixedWorkflowStatistics implements WorkflowStatistics
{
    /**
     * @param list<StateCount> $employers
     * @param list<StateCount> $mandates
     * @param list<StateCount> $declarations
     */
    public function __construct(private array $employers, private array $mandates, private array $declarations) {}

    public function employerStages(): array
    {
        return $this->employers;
    }

    public function mandateStates(): array
    {
        return $this->mandates;
    }

    public function declarationStates(): array
    {
        return $this->declarations;
    }
}
