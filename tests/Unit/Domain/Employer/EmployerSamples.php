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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\BankDetails;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Bic;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Iban;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\BankDetailsDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Fictitious values of the official documentation examples (api-reference §2.1).
 *
 * One static factory per fictitious sample, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class EmployerSamples
{
    public static function instant(string $iso = '2026-11-15T10:00:00Z'): Instant
    {
        return Instant::fromIso8601($iso);
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function externalReference(): ExternalReference
    {
        return ExternalReference::fromString('employer-ref-1');
    }

    public static function accountId(): EmployerAccountId
    {
        return EmployerAccountId::fromString('account-1');
    }

    public static function employer(): Employer
    {
        return Employer::of(CesuNumber::fromString('Z0003752390009'), CalendarDate::fromString('1975-01-30'));
    }

    public static function address(): Address
    {
        return new Address(
            StreetNumber::fromInt(12),
            StreetLetter::fromString('B'),
            StreetType::fromString('R'),
            AddressText::streetName('De la Paix'),
            AddressText::complement('Batiment C'),
            null,
            MunicipalityCode::fromString('69383'),
            PostalCode::fromString('69003'),
        );
    }

    public static function bankDetails(): BankDetails
    {
        return BankDetails::of(Iban::fromString('FR7630001007941234567890185'), Bic::fromString('BDFEFRPPCCT'), true);
    }

    public static function newEmployer(): NewEmployer
    {
        return new NewEmployer(
            Civility::Male,
            PersonName::lastName('Dupond'),
            PersonName::firstName('Pierre'),
            CalendarDate::fromString('1975-01-30'),
            EmailAddress::fromString('pierre.durand@example.com'),
            PhoneNumber::fromString('0412345678'),
            self::address(),
            self::bankDetails(),
        );
    }

    public static function newEmployerDraft(): NewEmployerDraft
    {
        return new NewEmployerDraft(
            '1',
            'Dupond',
            'Pierre',
            '1975-01-30',
            'pierre.durand@example.com',
            '0412345678',
            new AddressDraft(12, 'B', 'R', 'De la Paix', 'Batiment C', '', '69383', '69003'),
            new BankDetailsDraft('FR7630001007941234567890185', 'BDFEFRPPCCT', true),
        );
    }

    /**
     * A registered account whose events were released.
     */
    public static function account(): EmployerAccount
    {
        $account = EmployerAccount::register(
            self::accountId(),
            self::tenantId(),
            self::externalReference(),
            self::employer(),
            self::instant(),
        );
        $account->releaseEvents();

        return $account;
    }

    public static function status(bool $mandatePresent = false, bool $aiciActive = false): EmployerStatus
    {
        return new EmployerStatus(true, 'actif', 'actif', $mandatePresent, null, null, $aiciActive);
    }

    public static function statusWithCesuPlus(bool $mandatePresent, string $cesuPlusState): EmployerStatus
    {
        return new EmployerStatus(true, 'actif', 'actif', $mandatePresent, $cesuPlusState, null, false);
    }
}
