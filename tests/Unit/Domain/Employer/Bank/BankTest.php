<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Bank;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\BankDetails;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Bic;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Iban;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class BankTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, non-negative-int}>
     */
    public static function provideFactories(): iterable
    {
        yield 'iban' => ['iban', 'iban', 34];
        yield 'bic' => ['bic', 'bic', 11];
    }

    /**
     * @param non-negative-int $max
     */
    #[DataProvider('provideFactories')]
    public function testNormalisationAndLimits(string $factory, string $subject, int $max): void
    {
        static::assertSame('FR7630', self::create($factory, 'fr 76 30')->value());
        static::assertSame('A', self::create($factory, 'a')->value());
        static::assertSame(str_repeat('A', $max), self::create($factory, str_repeat('a', $max))->value());
        static::assertSame(str_repeat('A', $max), self::create($factory, ' ' . implode(' ', str_split(str_repeat('a', $max))) . ' ')->value());

        self::assertFailure(InvalidValue::empty($subject), $factory, '');
        self::assertFailure(InvalidValue::empty($subject), $factory, '   ');
        self::assertFailure(InvalidValue::tooLong($subject, $max), $factory, str_repeat('a', $max + 1));
        self::assertFailure(InvalidValue::invalidCharacters($subject), $factory, 'FR76-30');
        self::assertFailure(InvalidValue::invalidCharacters($subject), $factory, "FR76\n");
        self::assertFailure(InvalidValue::invalidCharacters($subject), $factory, "\tFR76");
        self::assertFailure(InvalidValue::invalidCharacters($subject), $factory, 'FRé6');
        self::assertFailure(InvalidValue::tooLong($subject, $max), $factory, 'F!' . str_repeat('A', $max));
    }

    private static function assertFailure(InvalidValue $expected, string $factory, string $value): void
    {
        try {
            self::create($factory, $value);
        } catch (InvalidValue $actual) {
            static::assertSame($expected->violationCode(), $actual->violationCode());
            static::assertSame($expected->params(), $actual->params());
            static::assertSame($expected->getMessage(), $actual->getMessage());

            return;
        }

        static::fail('Expected an InvalidValue.');
    }

    /**
     * @throws InvalidValue
     */
    private static function create(string $factory, string $value): Iban|Bic
    {
        return $factory === 'iban' ? Iban::fromString($value) : Bic::fromString($value);
    }

    public function testEqualityAndMasking(): void
    {
        static::assertTrue(Iban::fromString('FR76')->equals(Iban::fromString('fr76')));
        static::assertFalse(Iban::fromString('FR76')->equals(Iban::fromString('FR77')));
        static::assertTrue(Bic::fromString('BDFEFRPP')->equals(Bic::fromString('bdfefrpp')));
        static::assertFalse(Bic::fromString('BDFEFRPP')->equals(Bic::fromString('BDFEFRPA')));
        static::assertSame(['value' => '***'], Iban::fromString('FR76')->__debugInfo());
        static::assertSame(['value' => '***'], Bic::fromString('BDFEFRPP')->__debugInfo());
    }

    public function testBankDetails(): void
    {
        $iban = Iban::fromString('FR7630001007941234567890185');
        $bic = Bic::fromString('BDFEFRPPCCT');
        $details = BankDetails::of($iban, $bic, true);

        static::assertSame($iban, $details->iban());
        static::assertSame($bic, $details->bic());
        static::assertTrue($details->sepaMandate());
        static::assertFalse(BankDetails::of($iban, $bic, false)->sepaMandate());
    }

    public function testBankDetailsEquality(): void
    {
        $iban = Iban::fromString('FR7630001007941234567890185');
        $bic = Bic::fromString('BDFEFRPPCCT');
        $details = BankDetails::of($iban, $bic, true);

        static::assertTrue($details->equals(BankDetails::of(Iban::fromString('FR7630001007941234567890185'), Bic::fromString('BDFEFRPPCCT'), true)));
        static::assertFalse($details->equals(BankDetails::of(Iban::fromString('FR7630001007941234567890186'), $bic, true)));
        static::assertFalse($details->equals(BankDetails::of($iban, Bic::fromString('BDFEFRPPCCU'), true)));
        static::assertFalse($details->equals(BankDetails::of($iban, $bic, false)));
    }
}
