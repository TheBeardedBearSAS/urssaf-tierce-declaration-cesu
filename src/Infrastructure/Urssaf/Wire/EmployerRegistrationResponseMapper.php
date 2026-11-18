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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the 010 response `OutputInscriptionPe` (api-reference §2.1) to the Cesu number of the new employer account.
 * The number is trimmed (the official documentation carries stray spaces in its examples, §5 point 13).
 */
final readonly class EmployerRegistrationResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $payload): CesuNumber
    {
        try {
            return CesuNumber::fromString(trim($payload->requireString('numeroCesu')));
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('field "numeroCesu" is not valid', $exception);
        }
    }
}
