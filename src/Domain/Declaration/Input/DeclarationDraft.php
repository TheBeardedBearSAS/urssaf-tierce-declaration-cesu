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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\DeclaredEmployeeDraft;

/**
 * Raw salary declaration (050, api-reference §2.7), as received from the host application. An empty string counts as
 * missing. Paths of the violations are the property names, nested with "." and "[i]". Personal data through the
 * employee (ADR 0007).
 *
 * The public constructor is the factory (more than 5 parts).
 */
final readonly class DeclarationDraft
{
    public function __construct(
        public ?string $employerCesuNumber,
        public ?int $type,
        public ?EmploymentPeriodDraft $employmentPeriod,
        public ?string $paymentDate,
        public ?string $activityNature,
        public ?string $deposit,
        public ?HourlyIncomeDraft $hourlyIncome,
        public ?FosterCarerIncomeDraft $fosterCarerIncome,
        public ?DeclaredEmployeeDraft $employee,
        public ?FosterCarerApprovalDraft $approval,
    ) {}
}
