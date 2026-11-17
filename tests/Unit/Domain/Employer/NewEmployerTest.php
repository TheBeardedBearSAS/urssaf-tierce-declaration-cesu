<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\BankDetails;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Bic;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Iban;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;

final class NewEmployerTest extends TestCase
{
    public function testAccessors(): void
    {
        $employer = EmployerSamples::newEmployer();

        static::assertSame(Civility::Male, $employer->civility());
        static::assertSame('Dupond', $employer->lastName()->value());
        static::assertSame('Pierre', $employer->firstName()->value());
        static::assertSame('1975-01-30', $employer->birthDate()->toString());
        static::assertSame('pierre.durand@example.com', $employer->email()->value());
        static::assertSame('0412345678', $employer->phone()->value());
        static::assertTrue($employer->address()->equals(EmployerSamples::address()));
        static::assertTrue($employer->bankDetails()->equals(EmployerSamples::bankDetails()));
    }

    /**
     * @param array{civility?: Civility, lastName?: PersonName, firstName?: PersonName, birthDate?: CalendarDate, email?: EmailAddress, phone?: PhoneNumber, address?: Address, bankDetails?: BankDetails} $changes
     */
    private static function variant(array $changes): NewEmployer
    {
        return new NewEmployer(
            $changes['civility'] ?? Civility::Male,
            $changes['lastName'] ?? PersonName::lastName('Dupond'),
            $changes['firstName'] ?? PersonName::firstName('Pierre'),
            $changes['birthDate'] ?? CalendarDate::fromString('1975-01-30'),
            $changes['email'] ?? EmailAddress::fromString('pierre.durand@example.com'),
            $changes['phone'] ?? PhoneNumber::fromString('0412345678'),
            $changes['address'] ?? EmployerSamples::address(),
            $changes['bankDetails'] ?? EmployerSamples::bankDetails(),
        );
    }

    public function testEquality(): void
    {
        $employer = EmployerSamples::newEmployer();

        static::assertTrue($employer->equals(self::variant([])));
        static::assertFalse($employer->equals(self::variant(['civility' => Civility::Female])));
        static::assertFalse($employer->equals(self::variant(['lastName' => PersonName::lastName('Dupont')])));
        static::assertFalse($employer->equals(self::variant(['firstName' => PersonName::firstName('Paul')])));
        static::assertFalse($employer->equals(self::variant(['birthDate' => CalendarDate::fromString('1975-01-31')])));
        static::assertFalse($employer->equals(self::variant(['email' => EmailAddress::fromString('autre@example.com')])));
        static::assertFalse($employer->equals(self::variant(['phone' => PhoneNumber::fromString('0412345679')])));
        static::assertFalse($employer->equals(self::variant([
            'address' => new Address(null, null, null, AddressText::streetName('Rue'), null, null, null, PostalCode::fromString('69003')),
        ])));
        static::assertFalse($employer->equals(self::variant([
            'bankDetails' => BankDetails::of(Iban::fromString('FR76'), Bic::fromString('BDFEFRPP'), true),
        ])));
    }

    public function testPersonalDataIsMaskedInDebugOutput(): void
    {
        $employer = EmployerSamples::newEmployer();
        $debug = $employer->__debugInfo();

        static::assertSame(
            [
                'civility' => $employer->civility(),
                'lastName' => $employer->lastName(),
                'firstName' => $employer->firstName(),
                'birthDate' => '***',
                'email' => $employer->email(),
                'phone' => $employer->phone(),
                'address' => $employer->address(),
                'bankDetails' => $employer->bankDetails(),
            ],
            $debug,
        );
        $dump = print_r($employer, true);
        foreach (['1975', 'Dupond', 'Pierre', 'pierre.durand', '0412345678', '69003', 'FR7630001007941234567890185', 'BDFEFRPPCCT', 'De la Paix'] as $secret) {
            static::assertStringNotContainsString($secret, $dump);
        }
    }
}
