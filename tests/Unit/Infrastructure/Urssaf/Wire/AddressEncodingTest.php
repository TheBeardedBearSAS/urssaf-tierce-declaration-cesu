<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\AddressEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\RequestSamples;

final class AddressEncodingTest extends TestCase
{
    public function testEveryPartIsEncodedInSwaggerOrder(): void
    {
        $encoded = AddressEncoding::encode(RequestSamples::fullAddress());

        static::assertSame(
            [
                'numVoie' => 12,
                'lettreVoie' => 'B',
                'typeVoie' => 'R',
                'libelleVoie' => 'De la Paix',
                'complementLocalisation' => 'Batiment C',
                'lieuDit' => 'Le Hameau',
                'codeCommune' => '69383',
                'codePostal' => '69003',
            ],
            $encoded,
        );
        static::assertSame(
            '{"numVoie":12,"lettreVoie":"B","typeVoie":"R","libelleVoie":"De la Paix","complementLocalisation":"Batiment C","lieuDit":"Le Hameau","codeCommune":"69383","codePostal":"69003"}',
            JsonEncoder::encode($encoded),
        );
    }

    public function testAbsentPartsAreOmitted(): void
    {
        static::assertSame(
            ['lieuDit' => 'Le Hameau', 'codePostal' => '69003'],
            AddressEncoding::encode(RequestSamples::minimalAddress()),
        );
    }
}
