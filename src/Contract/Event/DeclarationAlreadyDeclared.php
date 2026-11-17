<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event;

/**
 * URSSAF answered that the declaration already exists (final, ADR 0006, ADR 0012): neither declared nor rejected by
 * this package, the host must act on it.
 *
 * - aggregateId: the declaration record id.
 */
final readonly class DeclarationAlreadyDeclared extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.declaration.already_declared';
}
