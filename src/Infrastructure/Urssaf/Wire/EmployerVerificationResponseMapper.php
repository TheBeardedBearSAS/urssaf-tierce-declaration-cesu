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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the 020 response `OutputVerificationPe` (api-reference §2.2). Raw strings are passed through: the Domain
 * ({@see EmployerStatus}) interprets the states and the blocking reason ("null", blank), unknown states are kept raw.
 *
 * `inscrit` is required; an absent `mandatPresent` or `etatAICI` reads as false (an unregistered employer may come
 * without them). The Swagger casing `motifBlocageCesuPlus` wins over the PDF casing `motifblocageCesuPlus`.
 */
final readonly class EmployerVerificationResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $payload): EmployerStatus
    {
        return new EmployerStatus(
            $payload->requireBool('inscrit'),
            $payload->optionalString('etatCompteEnLigne'),
            $payload->optionalString('etatMandatSepa'),
            $payload->optionalBool('mandatPresent') ?? false,
            $payload->optionalString('etatCesuPlus'),
            $payload->optionalString('motifBlocageCesuPlus') ?? $payload->optionalString('motifblocageCesuPlus'),
            $payload->optionalBool('etatAICI') ?? false,
        );
    }
}
