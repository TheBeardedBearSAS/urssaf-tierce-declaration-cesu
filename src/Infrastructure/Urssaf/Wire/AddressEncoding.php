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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;

/**
 * `Adresse` (api-reference §2.1), shared by the employer address of 010 (`adresseEmploi`) and the employee address of
 * 050 (`infosSalarie.adresse`). Absent parts are omitted, never sent as null.
 */
final class AddressEncoding
{
    /**
     * @return array<string, int|string>
     */
    public static function encode(Address $address): array
    {
        return array_filter(
            [
                'numVoie' => $address->number()?->value(),
                'lettreVoie' => $address->letter()?->value(),
                'typeVoie' => $address->type()?->value(),
                'libelleVoie' => $address->streetName()?->value(),
                'complementLocalisation' => $address->complement()?->value(),
                'lieuDit' => $address->locality()?->value(),
                'codeCommune' => $address->municipalityCode()?->value(),
                'codePostal' => $address->postalCode()->value(),
            ],
            static fn(int|string|null $value): bool => $value !== null,
        );
    }
}
