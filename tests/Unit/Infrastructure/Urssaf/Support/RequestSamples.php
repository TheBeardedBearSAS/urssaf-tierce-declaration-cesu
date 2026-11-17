<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalBasis;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DepartmentalCouncil;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerApproval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\HourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementSubCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\BirthDepartment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;

/**
 * Fictitious values shared by the request mapper tests (documentation examples and invented data only).
 */
final class RequestSamples
{
    /**
     * Every part of an address set (the 010 example of the PDF, `lieuDit` added).
     */
    public static function fullAddress(): Address
    {
        return new Address(
            StreetNumber::fromInt(12),
            StreetLetter::fromString('B'),
            StreetType::fromString('R'),
            AddressText::streetName('De la Paix'),
            AddressText::complement('Batiment C'),
            AddressText::locality('Le Hameau'),
            MunicipalityCode::fromString('69383'),
            PostalCode::fromString('69003'),
        );
    }

    /**
     * Only the mandatory parts: one location field (the locality) and the postal code.
     */
    public static function minimalAddress(): Address
    {
        return new Address(
            null,
            null,
            null,
            null,
            null,
            AddressText::locality('Le Hameau'),
            null,
            PostalCode::fromString('69003'),
        );
    }

    /**
     * Born in France: department required, every optional part present.
     */
    public static function fullIdentity(): EmployeeIdentity
    {
        return new EmployeeIdentity(
            PersonName::birthName('Martin'),
            PersonName::usageName('Durand'),
            PersonName::firstName('Julie'),
            CalendarDate::fromString('1990-05-12'),
            PersonName::birthCity('Lyon'),
            CountryCode::france(),
            BirthDepartment::fromString('69'),
        );
    }

    /**
     * Born abroad: no usage name, no birth date, no city, no department.
     */
    public static function minimalIdentity(): EmployeeIdentity
    {
        return new EmployeeIdentity(
            PersonName::birthName('Martin'),
            null,
            PersonName::firstName('Julie'),
            null,
            null,
            CountryCode::fromString('350'),
            null,
        );
    }

    /**
     * Born abroad with the birth date and the birth city that an identification by identity requires, and nothing
     * else.
     */
    public static function identityWithRequiredPartsOnly(): EmployeeIdentity
    {
        return new EmployeeIdentity(
            PersonName::birthName('Martin'),
            null,
            PersonName::firstName('Julie'),
            CalendarDate::fromString('1990-05-12'),
            PersonName::birthCity('Lyon'),
            CountryCode::fromString('350'),
            null,
        );
    }

    /**
     * An employee identified by NIR or by identity, every optional part present.
     */
    public static function fullEmployee(EmployeeReference $reference, EmploymentKind $kind): DeclaredEmployee
    {
        return new DeclaredEmployee(
            $reference,
            $kind,
            false,
            Civility::Female,
            self::fullAddress(),
            EmailAddress::fromString('julie.martin@example.org'),
            PhoneNumber::fromString('0102030405'),
        );
    }

    /**
     * A foster carer approval with every part set.
     */
    public static function fullApproval(): FosterCarerApproval
    {
        return FosterCarerApproval::of(
            ApprovalBasis::CertifiedApproval,
            DepartmentalCouncil::fromString('001'),
            ApprovalNumber::fromString('12345678910111213145'),
            CalendarDate::fromString('2019-01-01'),
            CalendarDate::fromString('2030-01-30'),
        );
    }

    /**
     * Every optional part of a foster carer declaration present: hourly income (overtime, paid leave hours, two
     * supplements, two leave periods), foster carer income, deposit and approval (the activity nature is forbidden
     * for a foster carer).
     */
    public static function maximalContent(DeclaredEmployee $employee): DeclarationContent
    {
        $hourlyIncome = HourlyIncome::of(
            DeclarationSamples::money('12.5'),
            WorkedHours::of(100, 8, 9),
            7,
            [
                DeclarationSamples::supplement(),
                SalarySupplement::of(
                    SupplementCategory::Bonus,
                    SupplementSubCategory::Bonus,
                    DeclarationSamples::money('50'),
                    null,
                    null,
                ),
            ],
            [
                DeclarationSamples::leave('2026-10-05', '2026-10-06', '1.5'),
                DeclarationSamples::leave('2026-10-20', '2026-10-21'),
            ],
        );

        return new DeclarationContent(
            DeclarationSamples::employerCesuNumber(),
            DeclarationSamples::period(),
            DeclarationSamples::date('2026-10-31'),
            $employee,
            $hourlyIncome,
            DeclarationSamples::fosterIncome(),
            DeclarationSamples::deposit(),
            self::fullApproval(),
            null,
        );
    }
}
