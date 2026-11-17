<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address;

/**
 * Cross-field rules of an address (api-reference §2.1 "numVoie" / "libelleVoie" conditions, PDF §3.1.4 "adresse
 * complète"), shared by {@see Address} (fail fast) and the input validator (collects every violation).
 *
 * The rules only depend on which fields are filled in.
 *
 * @phpstan-type Presence array{
 *     streetNumber: bool,
 *     streetLetter: bool,
 *     streetType: bool,
 *     streetName: bool,
 *     complement: bool,
 *     locality: bool,
 * }
 */
enum AddressRule
{
    /** lettreVoie requires numVoie. */
    case LetterRequiresNumber;

    /** numVoie requires libelleVoie. */
    case NumberRequiresStreetName;

    /** typeVoie requires libelleVoie. */
    case StreetTypeRequiresStreetName;

    /** At least one of the six location fields. */
    case LocationRequired;

    /**
     * @param Presence $presence
     */
    public function isViolatedBy(array $presence): bool
    {
        return match ($this) {
            self::LetterRequiresNumber => $presence['streetLetter'] && !$presence['streetNumber'],
            self::NumberRequiresStreetName => $presence['streetNumber'] && !$presence['streetName'],
            self::StreetTypeRequiresStreetName => $presence['streetType'] && !$presence['streetName'],
            self::LocationRequired => !$presence['streetNumber']
                && !$presence['streetLetter']
                && !$presence['streetType']
                && !$presence['streetName']
                && !$presence['complement']
                && !$presence['locality'],
        };
    }

    /**
     * Path of the missing field, relative to the address ("" = the address itself).
     */
    public function path(): string
    {
        return match ($this) {
            self::LetterRequiresNumber => 'streetNumber',
            self::NumberRequiresStreetName, self::StreetTypeRequiresStreetName => 'streetName',
            self::LocationRequired => '',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::LetterRequiresNumber => 'street_number.required_with_street_letter',
            self::NumberRequiresStreetName => 'street_name.required_with_street_number',
            self::StreetTypeRequiresStreetName => 'street_name.required_with_street_type',
            self::LocationRequired => 'address.location_required',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::LetterRequiresNumber => 'A street number is required when a street letter is given.',
            self::NumberRequiresStreetName => 'A street name is required when a street number is given.',
            self::StreetTypeRequiresStreetName => 'A street name is required when a street type is given.',
            self::LocationRequired => 'At least one of the street number, letter, type, name, the complement or the locality is required.',
        };
    }

    /**
     * @param Presence $presence
     *
     * @return list<self>
     */
    public static function violatedBy(array $presence): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn(self $rule): bool => $rule->isViolatedBy($presence),
        ));
    }
}
