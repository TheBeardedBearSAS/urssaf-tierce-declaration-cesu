<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the 070 response `OutputActivationAici` (api-reference §2.9): the required `activationAici` boolean.
 */
final readonly class AiciActivationResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $payload): AiciActivation
    {
        return AiciActivation::of($payload->requireBool('activationAici'));
    }
}
