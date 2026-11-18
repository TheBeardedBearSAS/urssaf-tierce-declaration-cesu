<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\BirthDepartment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Turns an {@see EmployeeIdentityDraft} into an {@see EmployeeIdentity}, reporting every violation under the path
 * "identity" (api-reference §2.2, §2.3, §2.7).
 *
 * Full mode (employee identified by the administrative data alone): the birth date and the birth city are required.
 * Partial mode (employee identified by NIR): they are optional.
 *
 * @internal
 */
final readonly class EmployeeIdentityParser
{
    private function __construct(private bool $full) {}

    public static function full(): self
    {
        return new self(true);
    }

    public static function partial(): self
    {
        return new self(false);
    }

    /**
     * Returns the identity, or null after reporting the violations to the collector.
     */
    public function parse(EmployeeIdentityDraft $draft, ViolationCollector $collector): ?EmployeeIdentity
    {
        $own = new ViolationCollector();
        $birthName = $own->required('birthName', 'birth_name', $draft->birthName, PersonName::birthName(...));
        $usageName = $own->optional('usageName', $draft->usageName, PersonName::usageName(...));
        $firstName = $own->required('firstName', 'first_name', $draft->firstName, PersonName::firstName(...));
        $birthCountry = $own->required('birthCountry', 'birth_country', $draft->birthCountry, CountryCode::fromString(...));
        $birthDepartment = $own->optional('birthDepartment', $draft->birthDepartment, BirthDepartment::fromString(...));
        if ($birthCountry?->isFrance() === true && !self::filled($draft->birthDepartment)) {
            $own->add(Violation::error(
                'birthDepartment',
                EmployeeIdentity::BIRTH_DEPARTMENT_REQUIRED,
                EmployeeIdentity::BIRTH_DEPARTMENT_REQUIRED_MESSAGE,
            ));
        }
        if ($this->full && !self::filled($draft->birthDate)) {
            $own->add(Violation::error(
                'birthDate',
                EmployeeIdentity::BIRTH_DATE_REQUIRED,
                EmployeeIdentity::BIRTH_DATE_REQUIRED_MESSAGE,
            ));
        }
        $birthDate = $own->optional('birthDate', $draft->birthDate, CalendarDate::fromString(...));
        if ($this->full && !self::filled($draft->birthCity)) {
            $own->add(Violation::error(
                'birthCity',
                EmployeeIdentity::BIRTH_CITY_REQUIRED,
                EmployeeIdentity::BIRTH_CITY_REQUIRED_MESSAGE,
            ));
        }
        $birthCity = $own->optional('birthCity', $draft->birthCity, PersonName::birthCity(...));
        $collector->merge($own->result(), 'identity');
        if ($own->errorCount() > 0) {
            return null;
        }
        // No error was reported: the required parts were parsed.
        /** @var PersonName $birthName */
        /** @var PersonName $firstName */
        /** @var CountryCode $birthCountry */
        return new EmployeeIdentity($birthName, $usageName, $firstName, $birthDate, $birthCity, $birthCountry, $birthDepartment);
    }

    private static function filled(?string $raw): bool
    {
        return $raw !== null && $raw !== '';
    }
}
