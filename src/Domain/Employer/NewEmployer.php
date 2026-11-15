<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\BankDetails;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;

/**
 * Registration of an employer, method 010 "InputInscriptionPe" (api-reference §2.1). The public constructor is the
 * factory (more than 5 parts). No invariant: the age (16 to 110), forbidden acronyms and e-mail uniqueness are
 * business errors raised by URSSAF (ADR 0009). Personal data (ADR 0007).
 */
final readonly class NewEmployer
{
    public function __construct(
        private Civility $civility,
        private PersonName $lastName,
        private PersonName $firstName,
        #[SensitiveParameter]
        private CalendarDate $birthDate,
        private EmailAddress $email,
        private PhoneNumber $phone,
        private Address $address,
        private BankDetails $bankDetails,
    ) {}

    public function civility(): Civility
    {
        return $this->civility;
    }

    public function lastName(): PersonName
    {
        return $this->lastName;
    }

    public function firstName(): PersonName
    {
        return $this->firstName;
    }

    public function birthDate(): CalendarDate
    {
        return $this->birthDate;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function phone(): PhoneNumber
    {
        return $this->phone;
    }

    public function address(): Address
    {
        return $this->address;
    }

    public function bankDetails(): BankDetails
    {
        return $this->bankDetails;
    }

    public function equals(self $other): bool
    {
        return $this->civility === $other->civility
            && $this->lastName->equals($other->lastName)
            && $this->firstName->equals($other->firstName)
            && $this->birthDate->equals($other->birthDate)
            && $this->email->equals($other->email)
            && $this->phone->equals($other->phone)
            && $this->address->equals($other->address)
            && $this->bankDetails->equals($other->bankDetails);
    }

    /**
     * Personal data: the birth date is masked in debug output (var_dump, print_r, dumpers).
     *
     * @return array{civility: Civility, lastName: PersonName, firstName: PersonName, birthDate: string, email: EmailAddress, phone: PhoneNumber, address: Address, bankDetails: BankDetails}
     */
    public function __debugInfo(): array
    {
        return [
            'civility' => $this->civility,
            'lastName' => $this->lastName,
            'firstName' => $this->firstName,
            'birthDate' => '***',
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'bankDetails' => $this->bankDetails,
        ];
    }
}
