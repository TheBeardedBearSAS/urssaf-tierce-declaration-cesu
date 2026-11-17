<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\BankDetails;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Bic;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Iban;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Turns a {@see NewEmployerDraft} into a {@see NewEmployer}, reporting every violation (notification pattern, ADR
 * 0000). Paths follow the draft ("lastName", "address.postalCode", "bankDetails.iban"). A missing address or bank
 * details draft is parsed as an all-missing one.
 *
 * @implements Validator<NewEmployerDraft>
 */
final readonly class NewEmployerFactory implements Validator
{
    public function validate(object $subject): ValidationResult
    {
        $collector = new ViolationCollector();
        $this->parse($subject, $collector);

        return $collector->result();
    }

    /**
     * @throws ValidationFailed with every violation
     */
    public function fromDraft(NewEmployerDraft $draft): NewEmployer
    {
        $collector = new ViolationCollector();

        return $this->parse($draft, $collector) ?? throw ValidationFailed::withResult($collector->result());
    }

    private function parse(NewEmployerDraft $draft, ViolationCollector $collector): ?NewEmployer
    {
        $civility = $collector->required('civility', 'civility', $draft->civility, Civility::fromCode(...));
        $lastName = $collector->required('lastName', 'last_name', $draft->lastName, PersonName::lastName(...));
        $firstName = $collector->required('firstName', 'first_name', $draft->firstName, PersonName::firstName(...));
        $birthDate = $collector->required('birthDate', 'birth_date', $draft->birthDate, CalendarDate::fromString(...));
        $email = $collector->required('email', 'email', $draft->email, EmailAddress::fromString(...));
        $phone = $collector->required('phone', 'phone', $draft->phone, PhoneNumber::fromString(...));
        $addressCollector = new ViolationCollector();
        $address = (new AddressValidator())->parse(
            $draft->address ?? new AddressDraft(null, null, null, null, null, null, null, null),
            $addressCollector,
        );
        $collector->merge($addressCollector->result(), 'address');
        $bankCollector = new ViolationCollector();
        $bankDetails = $this->parseBankDetails($draft->bankDetails ?? new BankDetailsDraft(null, null, null), $bankCollector);
        $collector->merge($bankCollector->result(), 'bankDetails');
        if (
            $civility === null
            || $lastName === null
            || $firstName === null
            || $birthDate === null
            || $email === null
            || $phone === null
            || !$address instanceof Address
            || !$bankDetails instanceof BankDetails
        ) {
            return null;
        }

        return new NewEmployer($civility, $lastName, $firstName, $birthDate, $email, $phone, $address, $bankDetails);
    }

    private function parseBankDetails(BankDetailsDraft $draft, ViolationCollector $collector): ?BankDetails
    {
        $iban = $collector->required('iban', 'iban', $draft->iban, Iban::fromString(...));
        $bic = $collector->required('bic', 'bic', $draft->bic, Bic::fromString(...));
        $sepaMandate = $collector->requiredBool('sepaMandate', 'sepa_mandate', $draft->sepaMandate);
        if ($iban === null || $bic === null || $sepaMandate === null) {
            return null;
        }

        return BankDetails::of($iban, $bic, $sepaMandate);
    }
}
