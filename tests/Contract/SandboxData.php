<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationContext;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\EmploymentPeriodDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\HourlyIncomeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\DeclaredEmployeeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\BirthMunicipalityName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\CivilName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\BankDetailsDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ExemptionCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\GeographicZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SalaryType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedHourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Fictitious data only (never a real person), unique per run where the sandbox requires it. The fictitious Cesu numbers
 * are assumed absent from the sandbox: an observation "registered" is reported, not failed.
 */
final class SandboxData
{
    /** Civil status of the fictitious employer registered by 010 and activated by 070. */
    public const NEW_EMPLOYER_BIRTH_DATE = '1975-01-30';

    private const TIME_ZONE = 'Europe/Paris';

    public static function fictitiousEmployerCesuNumber(): string
    {
        return 'Z0000000000000';
    }

    public static function fictitiousEmployeeCesuNumber(): string
    {
        return '00000000000000';
    }

    public static function fictitiousBirthDate(): string
    {
        return '1980-03-29';
    }

    public static function fictitiousEmployer(): Employer
    {
        return Employer::of(
            CesuNumber::fromString(self::fictitiousEmployerCesuNumber()),
            CalendarDate::fromString(self::fictitiousBirthDate()),
        );
    }

    /**
     * The official example of the estimate method (docs/api-reference.md section 2.6, tests/Fixtures/wire/estimate_request.json).
     */
    public static function officialEstimateRequest(): EstimateRequest
    {
        return EstimateRequest::of(
            EmploymentKind::HomeEmployment,
            new SimulatedHourlyIncome(
                Money::fromCents(1000),
                WorkedHours::of(12, 0, 0),
                0,
                [],
                SalaryType::Net,
                ExemptionCode::NoExemption,
                GeographicZone::Metropolitan,
                true,
            ),
            null,
        );
    }

    /**
     * A fictitious employer with a unique e-mail address and phone number (the sandbox refuses known ones).
     */
    public static function newEmployer(): NewEmployer
    {
        return (new NewEmployerFactory())->fromDraft(new NewEmployerDraft(
            '1',
            'Contrat',
            'Test',
            self::NEW_EMPLOYER_BIRTH_DATE,
            \sprintf('contract.%s@example.com', bin2hex(random_bytes(6))),
            '06' . str_pad((string) random_int(0, 99_999_999), 8, '0', \STR_PAD_LEFT),
            new AddressDraft(12, 'B', 'R', 'De la Paix', 'Batiment C', '', '69383', '69003'),
            new BankDetailsDraft('FR7630001007941234567890185', 'BDFEFRPPCCT', true),
        ));
    }

    /**
     * 070 for the employer created by 010 (same fictitious civil status; "nomUsage" is the birth name).
     */
    public static function aiciRequestFor(Employer $employer): AiciActivationRequest
    {
        return new AiciActivationRequest(
            $employer,
            Civility::Male,
            CivilName::birthName('Contrat'),
            CivilName::usageName('Contrat'),
            CivilName::firstName('Test'),
            CountryCode::france(),
            MunicipalityCode::fromString('69381'),
            BirthMunicipalityName::fromString('LYON 01'),
        );
    }

    /**
     * A standard declaration for the previous calendar month: the pair and the period are the duplicate key, so it is
     * the same declaration for every run of a month.
     */
    public static function standardDeclaration(Employer $employer, CesuNumber $employee): Declaration
    {
        $now = new DateTimeImmutable('now', new DateTimeZone(self::TIME_ZONE));
        $first = $now->modify('first day of last month')->format('Y-m-d');
        $last = $now->modify('last day of last month')->format('Y-m-d');

        return (new DeclarationFactory())->fromDraft(
            new DeclarationDraft(
                $employer->cesuNumber()->value(),
                DeclarationType::Standard->value,
                new EmploymentPeriodDraft($first, $last),
                $last,
                null,
                null,
                new HourlyIncomeDraft('12.00', 2, 0, 0, null),
                null,
                new DeclaredEmployeeDraft($employee->value(), null, null, null, null, null, null, EmploymentKind::HomeEmployment->value, true),
                null,
            ),
            DeclarationContext::of(CalendarDate::fromString($now->format('Y-m-d')), false),
        );
    }
}
