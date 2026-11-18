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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the `Cotisations` object of the 040 and 050 responses (api-reference §2.6, §2.7): both amounts are required.
 */
final readonly class ContributionsMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $payload): Contributions
    {
        return Contributions::of(
            MoneyEncoding::decode($payload->requireNumber('cotisationsPatronales')),
            MoneyEncoding::decode($payload->requireNumber('cotisationsSalariales')),
        );
    }
}
