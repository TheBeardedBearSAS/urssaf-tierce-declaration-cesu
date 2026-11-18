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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Employment kind "typeEmploi" (api-reference §3.2): PE = home employment, AF = foster carer.
 */
enum EmploymentKind: string
{
    case HomeEmployment = 'PE';
    case FosterCarer = 'AF';

    /**
     * @throws InvalidValue "employment_kind.unknown"
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::unknown('employment_kind');
    }

    public function isFosterCarer(): bool
    {
        return $this === self::FosterCarer;
    }
}
