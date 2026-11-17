<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;

/**
 * Query parameters of the operations that have no body: 020, 021, 030, 031 and 060 (api-reference §2.2 to §2.8).
 *
 * The parameters carry personal data (NIR, names, birth dates): a query string is never logged and never put in an
 * exception message (ADR 0007). An employee referenced by NIR is sent as NIR only: the administrative data are not
 * checked against a NIR (api-reference §2.3).
 */
final class QueryEncoding
{
    /**
     * 020. The path is {@see ApiPaths::verifyEmployer()}.
     *
     * @return array<string, string>
     *
     * @throws InvariantViolation "wire.unsupported_employee_reference"
     */
    public static function employerVerification(EmployerVerification $verification): array
    {
        $parameters = ['dateNaissance' => DateEncoding::encode($verification->employer()->birthDate())];
        $employee = $verification->employee();

        return $employee instanceof EmployeeReference ? $parameters + self::employeeParameters($employee, 'Sala') : $parameters;
    }

    /**
     * 021.
     *
     * @return array<string, string>
     *
     * @throws InvariantViolation "wire.unsupported_employee_reference"
     */
    public static function employeeVerification(EmployeeReference $employee): array
    {
        return self::employeeParameters($employee, '');
    }

    /**
     * 030 and 031.
     *
     * @return array<string, string>
     */
    public static function mandate(Employer $employer): array
    {
        return [
            'numeroCesu' => $employer->cesuNumber()->value(),
            'dateNaissance' => DateEncoding::encode($employer->birthDate()),
        ];
    }

    /**
     * 060. The path is {@see ApiPaths::activateCesuPlus()}.
     *
     * @return array<string, string>
     */
    public static function cesuPlusActivation(CesuPlusActivationRequest $request, AgreementEncoding $agreementEncoding): array
    {
        return [
            'dateNaissance' => DateEncoding::encode($request->employer()->birthDate()),
            'numeroCesuSalarie' => $request->employeeCesuNumber()->value(),
            'accord' => $agreementEncoding->encode($request->employeeAgreement()),
        ];
    }

    /**
     * Employee lookup parameters; the suffix is `Sala` on 020 and empty on 021.
     *
     * @return array<string, string>
     *
     * @throws InvariantViolation "wire.unsupported_employee_reference"
     */
    public static function employeeParameters(EmployeeReference $employee, string $suffix): array
    {
        $parts = EmployeeReferenceParts::of($employee);
        $identity = $parts->nir() instanceof Nir ? null : $parts->identity();

        return array_filter(
            [
                'numeroCesu' . $suffix => $parts->cesuNumber()?->value(),
                'nir' . $suffix => $parts->nir()?->value(),
            ],
            static fn(?string $value): bool => $value !== null,
        ) + ($identity instanceof EmployeeIdentity ? self::identityParameters($identity, $suffix) : []);
    }

    /**
     * Administrative data parameters; absent optional values are omitted.
     *
     * @return array<string, string>
     */
    public static function identityParameters(EmployeeIdentity $identity, string $suffix): array
    {
        return array_filter(
            [
                'prenom' . $suffix => $identity->firstName()->value(),
                'nomNaissance' . $suffix => $identity->birthName()->value(),
                'nomUsage' . $suffix => $identity->usageName()?->value(),
                'dateNaissance' . $suffix => self::date($identity),
                'departementNaissance' . $suffix => $identity->birthDepartment()?->value(),
                'villeNaissance' . $suffix => $identity->birthCity()?->value(),
                'codePaysNaissance' . $suffix => $identity->birthCountry()->value(),
            ],
            static fn(?string $value): bool => $value !== null,
        );
    }

    private static function date(EmployeeIdentity $identity): ?string
    {
        $birthDate = $identity->birthDate();

        return $birthDate instanceof CalendarDate ? DateEncoding::encode($birthDate) : null;
    }
}
