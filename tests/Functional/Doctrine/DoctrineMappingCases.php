<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;

/**
 * Data providers of {@see DoctrineMappingTest}: the case names understood by {@see DoctrineMappingSamples}.
 */
final class DoctrineMappingCases
{
    private function __construct() {}

    /**
     * @return iterable<string, array{string}> case names of {@see DoctrineMappingSamples::account()}
     */
    public static function accounts(): iterable
    {
        yield 'registered, without status' => ['registered'];
        yield 'imported' => ['imported'];
        yield 'with a status read for a Cesu number employee' => ['status-employee'];
        yield 'with a status read for no employee' => ['status'];
        yield 'with an all-null status' => ['status-nulls'];
        yield 'with a status carrying unknown raw codes' => ['status-unknown'];
        foreach (EmployerStage::cases() as $stage) {
            yield 'stage ' . $stage->value => ['stage:' . $stage->value];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mandates(): iterable
    {
        foreach (MandateState::cases() as $state) {
            if ($state !== MandateState::Confirmed) {
                yield $state->value . ', without last check' => [$state->value . ':nocheck'];
            }
            yield $state->value . ', with last check' => [$state->value . ':check'];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function records(): iterable
    {
        foreach (DeclarationState::cases() as $state) {
            yield 'state ' . $state->value => ['state:' . $state->value];
        }
        yield 'Cesu+ declaration' => ['cesu-plus'];
        yield 'foster carer declaration' => ['foster-carer'];
        yield 'employee referenced by NIR' => ['nir'];
    }
}
