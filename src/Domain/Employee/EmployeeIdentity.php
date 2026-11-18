<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;

/**
 * Administrative data of an employee (api-reference §2.2, §2.3, §2.7): birth name, usage name, first name, birth date,
 * birth city, birth country and birth department. The public constructor is the factory (more than 5 parts).
 *
 * The birth department is required for an employee born in France; one given for a birth abroad is accepted (no
 * published rule forbids it). Personal data (ADR 0007).
 */
final readonly class EmployeeIdentity
{
    public const BIRTH_DEPARTMENT_REQUIRED = 'employee_identity.birth_department_required';
    public const BIRTH_DATE_REQUIRED = 'employee_identity.birth_date_required';
    public const BIRTH_CITY_REQUIRED = 'employee_identity.birth_city_required';
    public const BIRTH_DEPARTMENT_REQUIRED_MESSAGE = 'The birth department is required for an employee born in France.';
    public const BIRTH_DATE_REQUIRED_MESSAGE = 'The birth date is required.';
    public const BIRTH_CITY_REQUIRED_MESSAGE = 'The birth city is required.';

    /**
     * @throws InvalidValue "employee_identity.birth_department_required"
     */
    public function __construct(
        private PersonName $birthName,
        private ?PersonName $usageName,
        private PersonName $firstName,
        #[SensitiveParameter]
        private ?CalendarDate $birthDate,
        private ?PersonName $birthCity,
        private CountryCode $birthCountry,
        private ?BirthDepartment $birthDepartment,
    ) {
        if ($birthCountry->isFrance() && !$birthDepartment instanceof BirthDepartment) {
            throw InvalidValue::because(self::BIRTH_DEPARTMENT_REQUIRED, self::BIRTH_DEPARTMENT_REQUIRED_MESSAGE);
        }
    }

    public function birthName(): PersonName
    {
        return $this->birthName;
    }

    public function usageName(): ?PersonName
    {
        return $this->usageName;
    }

    public function firstName(): PersonName
    {
        return $this->firstName;
    }

    public function birthDate(): ?CalendarDate
    {
        return $this->birthDate;
    }

    public function birthCity(): ?PersonName
    {
        return $this->birthCity;
    }

    public function birthCountry(): CountryCode
    {
        return $this->birthCountry;
    }

    public function birthDepartment(): ?BirthDepartment
    {
        return $this->birthDepartment;
    }

    public function equals(self $other): bool
    {
        return $this->birthName->equals($other->birthName)
            && $this->usageName?->value() === $other->usageName?->value()
            && $this->firstName->equals($other->firstName)
            && $this->birthDate?->toString() === $other->birthDate?->toString()
            && $this->birthCity?->value() === $other->birthCity?->value()
            && $this->birthCountry->equals($other->birthCountry)
            && $this->birthDepartment?->value() === $other->birthDepartment?->value();
    }

    /**
     * Personal data: the birth date is masked in debug output (var_dump, print_r, dumpers).
     *
     * @return array{birthName: PersonName, usageName: PersonName|null, firstName: PersonName, birthDate: string|null, birthCity: PersonName|null, birthCountry: CountryCode, birthDepartment: BirthDepartment|null}
     */
    public function __debugInfo(): array
    {
        return [
            'birthName' => $this->birthName,
            'usageName' => $this->usageName,
            'firstName' => $this->firstName,
            'birthDate' => $this->birthDate instanceof CalendarDate ? '***' : null,
            'birthCity' => $this->birthCity,
            'birthCountry' => $this->birthCountry,
            'birthDepartment' => $this->birthDepartment,
        ];
    }
}
