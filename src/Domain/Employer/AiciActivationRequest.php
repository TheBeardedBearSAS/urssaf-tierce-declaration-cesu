<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\BirthMunicipalityName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\CivilName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;

/**
 * Input of method 070, AICI activation ("InputActivationAici", api-reference §2.9): the employer (path "numeroCesu" and
 * body "dateNaissance") and the civil status the tax administration checks.
 *
 * "nomUsage" is required by the contract (api-reference §5): an employer without usage name passes the birth name.
 * Municipality data given for a birth abroad is accepted and sent (nothing forbids it). The names and the municipality
 * are personal data and mask themselves (ADR 0007). The constructor is the factory (more than 5 parts).
 */
final readonly class AiciActivationRequest
{
    public const BIRTH_COUNTRY_INVALID = 'aici_activation_request.birth_country_invalid';
    public const BIRTH_MUNICIPALITY_CODE_REQUIRED = 'aici_activation_request.birth_municipality_code_required';
    public const BIRTH_MUNICIPALITY_NAME_REQUIRED = 'aici_activation_request.birth_municipality_name_required';
    private const COUNTRY_CODE_LENGTH = 3;

    /**
     * @throws InvalidValue "aici_activation_request.birth_country_invalid" unless the country code has 3 characters;
     *                      "aici_activation_request.birth_municipality_code_required" or
     *                      "aici_activation_request.birth_municipality_name_required" for a birth in France
     */
    public function __construct(
        private Employer $employer,
        private Civility $civility,
        private CivilName $birthName,
        private CivilName $usageName,
        private CivilName $firstName,
        private CountryCode $birthCountry,
        private ?MunicipalityCode $birthMunicipalityCode,
        private ?BirthMunicipalityName $birthMunicipalityName,
    ) {
        if (mb_strlen($birthCountry->value()) !== self::COUNTRY_CODE_LENGTH) {
            throw InvalidValue::because(
                self::BIRTH_COUNTRY_INVALID,
                'The birth country must be a 3-character INSEE country code.',
            );
        }
        if ($birthCountry->isFrance() && !$birthMunicipalityCode instanceof MunicipalityCode) {
            throw InvalidValue::because(
                self::BIRTH_MUNICIPALITY_CODE_REQUIRED,
                'The birth municipality code is required for a birth in France.',
            );
        }
        if ($birthCountry->isFrance() && !$birthMunicipalityName instanceof BirthMunicipalityName) {
            throw InvalidValue::because(
                self::BIRTH_MUNICIPALITY_NAME_REQUIRED,
                'The birth municipality name is required for a birth in France.',
            );
        }
    }

    public function employer(): Employer
    {
        return $this->employer;
    }

    public function civility(): Civility
    {
        return $this->civility;
    }

    public function birthName(): CivilName
    {
        return $this->birthName;
    }

    public function usageName(): CivilName
    {
        return $this->usageName;
    }

    public function firstName(): CivilName
    {
        return $this->firstName;
    }

    public function birthCountry(): CountryCode
    {
        return $this->birthCountry;
    }

    public function birthMunicipalityCode(): ?MunicipalityCode
    {
        return $this->birthMunicipalityCode;
    }

    public function birthMunicipalityName(): ?BirthMunicipalityName
    {
        return $this->birthMunicipalityName;
    }

    public function equals(self $other): bool
    {
        return $this->employer->equals($other->employer)
            && $this->civility === $other->civility
            && $this->birthName->equals($other->birthName)
            && $this->usageName->equals($other->usageName)
            && $this->firstName->equals($other->firstName)
            && $this->birthCountry->equals($other->birthCountry)
            && $this->birthMunicipalityCode?->value() === $other->birthMunicipalityCode?->value()
            && $this->birthMunicipalityName?->value() === $other->birthMunicipalityName?->value();
    }

    /**
     * Personal data: the employer and the value objects mask themselves in debug output.
     *
     * @return array{employer: Employer, civility: Civility, birthName: CivilName, usageName: CivilName, firstName: CivilName, birthCountry: CountryCode, birthMunicipalityCode: ?MunicipalityCode, birthMunicipalityName: ?BirthMunicipalityName}
     */
    public function __debugInfo(): array
    {
        return [
            'employer' => $this->employer,
            'civility' => $this->civility,
            'birthName' => $this->birthName,
            'usageName' => $this->usageName,
            'firstName' => $this->firstName,
            'birthCountry' => $this->birthCountry,
            'birthMunicipalityCode' => $this->birthMunicipalityCode,
            'birthMunicipalityName' => $this->birthMunicipalityName,
        ];
    }
}
