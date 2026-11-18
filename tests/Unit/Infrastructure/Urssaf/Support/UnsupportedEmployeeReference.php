<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * An employee reference of an implementation unknown to the wire layer (covers the refusal of
 * {@see \TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployeeReferenceParts}).
 */
final readonly class UnsupportedEmployeeReference implements EmployeeReference
{
    public function kind(): EmployeeReferenceKind
    {
        return EmployeeReferenceKind::Identity;
    }

    public function cesuNumber(): ?CesuNumber
    {
        return null;
    }

    public function equals(EmployeeReference $other): bool
    {
        return false;
    }
}
