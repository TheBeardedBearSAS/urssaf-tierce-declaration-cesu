<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use Stringable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Departmental council (`codeConseilDepartemental`, api-reference §3.11): the 101 published codes, `001` to `095`
 * except `020`, with Corsica as `02A` and `02B`, then `971`, `972`, `973`, `974` and `976` (there is no `975`).
 */
final readonly class DepartmentalCouncil implements Stringable
{
    private const SUBJECT = 'departmental_council';

    private const CODES = [
        '001',
        '002',
        '003',
        '004',
        '005',
        '006',
        '007',
        '008',
        '009',
        '010',
        '011',
        '012',
        '013',
        '014',
        '015',
        '016',
        '017',
        '018',
        '019',
        '021',
        '022',
        '023',
        '024',
        '025',
        '026',
        '027',
        '028',
        '029',
        '02A',
        '02B',
        '030',
        '031',
        '032',
        '033',
        '034',
        '035',
        '036',
        '037',
        '038',
        '039',
        '040',
        '041',
        '042',
        '043',
        '044',
        '045',
        '046',
        '047',
        '048',
        '049',
        '050',
        '051',
        '052',
        '053',
        '054',
        '055',
        '056',
        '057',
        '058',
        '059',
        '060',
        '061',
        '062',
        '063',
        '064',
        '065',
        '066',
        '067',
        '068',
        '069',
        '070',
        '071',
        '072',
        '073',
        '074',
        '075',
        '076',
        '077',
        '078',
        '079',
        '080',
        '081',
        '082',
        '083',
        '084',
        '085',
        '086',
        '087',
        '088',
        '089',
        '090',
        '091',
        '092',
        '093',
        '094',
        '095',
        '971',
        '972',
        '973',
        '974',
        '976',
    ];

    private function __construct(private string $value) {}

    /**
     * The code is upper-cased (`02a` is `02A`).
     *
     * @throws InvalidValue "departmental_council.unknown"
     */
    public static function fromString(string $value): self
    {
        $code = strtoupper($value);
        if (!\in_array($code, self::CODES, true)) {
            throw InvalidValue::unknown(self::SUBJECT);
        }

        return new self($code);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
