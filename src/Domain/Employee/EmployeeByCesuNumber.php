<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Employee identified by the Cesu number "numeroCesu" (api-reference §2.2, §2.3, §2.7).
 */
final readonly class EmployeeByCesuNumber implements EmployeeReference
{
    private function __construct(private CesuNumber $cesuNumber) {}

    public static function of(CesuNumber $cesuNumber): self
    {
        return new self($cesuNumber);
    }

    public function kind(): EmployeeReferenceKind
    {
        return EmployeeReferenceKind::CesuNumber;
    }

    public function cesuNumber(): CesuNumber
    {
        return $this->cesuNumber;
    }

    public function equals(EmployeeReference $other): bool
    {
        return $other instanceof self && $this->cesuNumber->equals($other->cesuNumber);
    }
}
