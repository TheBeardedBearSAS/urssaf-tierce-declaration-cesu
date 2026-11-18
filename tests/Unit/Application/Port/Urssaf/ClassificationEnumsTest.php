<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;

final class ClassificationEnumsTest extends TestCase
{
    public function testErrorKindValues(): void
    {
        static::assertSame(
            [
                'invalid_parameter',
                'business',
                'sepa_missing',
                'mandate_missing',
                'rib_missing',
                'mandate_held_elsewhere',
                'account_unknown',
                'aici_not_active',
                'nature_incompatible',
                'nature_forbidden_for_foster_carer',
                'nature_unknown',
                'nature_missing',
                'deposit_too_high',
                'technical',
                'unknown',
            ],
            array_map(static fn(ErrorKind $kind): string => $kind->value, ErrorKind::cases()),
        );
    }

    public function testBusinessErrorValues(): void
    {
        $values = array_map(static fn(BusinessError $error): string => $error->value, BusinessError::cases());

        static::assertCount(87, BusinessError::cases());
        static::assertSame($values, array_values(array_unique($values)));
        foreach ($values as $value) {
            static::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $value);
        }
        static::assertSame(BusinessError::DuplicateDeclaration, BusinessError::from('duplicate_declaration'));
    }
}
