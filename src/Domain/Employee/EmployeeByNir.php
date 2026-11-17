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
 * Employee identified by the NIR (api-reference §2.3, §2.7), with the administrative data when they are known: the
 * lookups (020, 021) send the NIR alone, a declaration requires the data (see {@see DeclaredEmployee}). Personal data.
 */
final readonly class EmployeeByNir implements EmployeeReference
{
    private function __construct(
        private Nir $nir,
        private ?EmployeeIdentity $identity,
    ) {}

    public static function of(Nir $nir, ?EmployeeIdentity $identity): self
    {
        return new self($nir, $identity);
    }

    public function kind(): EmployeeReferenceKind
    {
        return EmployeeReferenceKind::Nir;
    }

    public function cesuNumber(): ?CesuNumber
    {
        return null;
    }

    public function nir(): Nir
    {
        return $this->nir;
    }

    public function identity(): ?EmployeeIdentity
    {
        return $this->identity;
    }

    public function equals(EmployeeReference $other): bool
    {
        return $other instanceof self
            && $this->nir->equals($other->nir)
            && ($this->identity instanceof EmployeeIdentity
                ? $other->identity instanceof EmployeeIdentity && $this->identity->equals($other->identity)
                : !$other->identity instanceof EmployeeIdentity);
    }
}
