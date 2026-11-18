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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\EmploymentPeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * A declaration recorded by the simulator: only what the duplicate rule needs, never the personal data.
 *
 * Source: reference `SimulatedPaymentRequest` (shape), lot 5 design §4.4 (050 duplicate rule), §4.5.
 *
 * @internal
 */
final readonly class SimulatedDeclaration
{
    public function __construct(
        public DeclarationId $id,
        private CesuNumber $employer,
        private EmployeeReference $employee,
        private EmploymentPeriod $period,
    ) {}

    /**
     * Same employer, same employee reference (same kind and value) and same employment period.
     */
    public function duplicates(Declaration $declaration): bool
    {
        $content = $declaration->content();

        return $this->employer->equals($content->employerCesuNumber())
            && $this->employee->equals($content->employee()->reference())
            && $this->period->equals($content->employmentPeriod());
    }
}
