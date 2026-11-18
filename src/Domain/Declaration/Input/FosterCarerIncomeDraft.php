<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

/**
 * Raw foster carer income (`RevenusAf`): every field is a decimal string, all optional.
 *
 * The public constructor is the factory (more than 5 parts).
 */
final readonly class FosterCarerIncomeDraft
{
    public function __construct(
        public ?string $housingAllowance,
        public ?string $maintenanceAllowanceDays,
        public ?string $particularConstraintDays,
        public ?string $fullTimeReceptionDays,
        public ?string $partTimeReceptionDays,
        public ?string $guaranteedMinimumPerMaintenanceDay,
        public ?string $smicPerParticularConstraintDay,
        public ?string $smicPerFullTimeReceptionDay,
        public ?string $smicPerPartTimeReceptionDay,
    ) {}
}
