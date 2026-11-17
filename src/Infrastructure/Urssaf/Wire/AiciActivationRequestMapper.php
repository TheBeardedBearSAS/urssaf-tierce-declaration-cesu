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

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;

/**
 * Method 070 (activate AICI): builds the `InputActivationAici` body (api-reference §2.9). The path is
 * {@see ApiPaths::activateAici()}.
 */
final readonly class AiciActivationRequestMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toBody(#[SensitiveParameter] AiciActivationRequest $request): array
    {
        return array_filter(
            [
                'nomNaissance' => $request->birthName()->value(),
                'nomUsage' => $request->usageName()->value(),
                'prenom' => $request->firstName()->value(),
                'codeCivilite' => $request->civility()->value,
                'dateNaissance' => DateEncoding::encode($request->employer()->birthDate()),
                'codePaysNaissance' => $request->birthCountry()->value(),
                'codeCommuneNaissance' => $request->birthMunicipalityCode()?->value(),
                'libelleCommuneNaissance' => $request->birthMunicipalityName()?->value(),
            ],
            static fn(?string $value): bool => $value !== null,
        );
    }
}
