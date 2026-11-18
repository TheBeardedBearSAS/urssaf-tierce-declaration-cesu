<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Address;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class CodesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFactories(): iterable
    {
        yield 'municipality code' => ['municipality', 'municipality_code'];
        yield 'postal code' => ['postal', 'postal_code'];
    }

    #[DataProvider('provideFactories')]
    public function testFiveAlphanumericCharacters(string $factory, string $subject): void
    {
        static::assertSame('69383', self::create($factory, '69383')->value());
        static::assertSame('2A004', self::create($factory, '2A004')->value());
        static::assertSame('ab1Z9', self::create($factory, 'ab1Z9')->value());
        foreach (['', '6938', '693833', '6938 ', ' 6938', "69383\n", "\n69383", '6938é', '69-83'] as $invalid) {
            try {
                self::create($factory, $invalid);
                static::fail('Expected an InvalidValue.');
            } catch (InvalidValue $invalidValue) {
                static::assertSame($subject . '.invalid_format', $invalidValue->violationCode());
                static::assertSame(['expected_format' => '5 alphanumeric characters'], $invalidValue->params());
                static::assertSame(
                    InvalidValue::invalidFormat($subject, '5 alphanumeric characters')->getMessage(),
                    $invalidValue->getMessage(),
                );
            }
        }
    }

    public function testEqualityAndMasking(): void
    {
        static::assertTrue(MunicipalityCode::fromString('69383')->equals(MunicipalityCode::fromString('69383')));
        static::assertFalse(MunicipalityCode::fromString('69383')->equals(MunicipalityCode::fromString('69384')));
        static::assertTrue(PostalCode::fromString('69003')->equals(PostalCode::fromString('69003')));
        static::assertFalse(PostalCode::fromString('69003')->equals(PostalCode::fromString('69004')));
        static::assertSame(['value' => '***'], MunicipalityCode::fromString('69383')->__debugInfo());
        static::assertSame(['value' => '***'], PostalCode::fromString('69003')->__debugInfo());
    }

    /**
     * @throws InvalidValue
     */
    private static function create(string $factory, string $value): MunicipalityCode|PostalCode
    {
        return $factory === 'municipality' ? MunicipalityCode::fromString($value) : PostalCode::fromString($value);
    }
}
