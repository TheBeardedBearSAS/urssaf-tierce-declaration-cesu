<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;

/**
 * Query of method 020 (api-reference §2.2): the employer, optionally with the employee whose Cesu+ link is checked.
 *
 * The lookup mode is the kind of the employee reference. An {@see \TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir}
 * carrying an identity is sent as NIR only (the PDF does not check administrative data with a NIR). Whether an
 * employer-only query is valid is an open point (api-reference §5): both shapes are allowed.
 */
final readonly class EmployerVerification
{
    private function __construct(
        private Employer $employer,
        private ?EmployeeReference $employee,
    ) {}

    public static function employerOnly(Employer $employer): self
    {
        return new self($employer, null);
    }

    public static function forPair(Employer $employer, EmployeeReference $employee): self
    {
        return new self($employer, $employee);
    }

    public function employer(): Employer
    {
        return $this->employer;
    }

    public function employee(): ?EmployeeReference
    {
        return $this->employee;
    }
}
