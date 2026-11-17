<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer;

/**
 * How an {@see EmployerAccount} came to be tracked (ADR 0006).
 */
enum RegistrationOrigin: string
{
    /** Created by method 010 through this package. */
    case Registered = 'registered';

    /** Existing Cesu employer adopted by the platform. */
    case Imported = 'imported';
}
