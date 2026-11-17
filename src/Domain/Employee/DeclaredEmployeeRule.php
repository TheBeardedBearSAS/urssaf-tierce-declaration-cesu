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

/**
 * Cross-field rules of the employee of a declaration "InfosSalarie" (api-reference §2.7, §4.7, PDF §3.7.4 / §3.7.5),
 * shared by {@see DeclaredEmployee} (fail fast) and the input parser (collects every violation).
 *
 * The rules only depend on how the employee is identified and on which fields are filled in.
 *
 * @phpstan-type Presence array{
 *     identity: bool,
 *     civility: bool,
 *     address: bool,
 *     email: bool,
 *     phone: bool,
 * }
 */
enum DeclaredEmployeeRule
{
    case CivilityForbiddenWithCesuNumber;
    case AddressForbiddenWithCesuNumber;
    case EmailForbiddenWithCesuNumber;
    case PhoneForbiddenWithCesuNumber;
    case CivilityRequired;
    case AddressRequired;
    case IdentityRequiredWithNir;

    /**
     * @param Presence $presence
     */
    public function isViolatedBy(EmployeeReferenceKind $kind, array $presence): bool
    {
        $byCesuNumber = $kind === EmployeeReferenceKind::CesuNumber;

        return match ($this) {
            self::CivilityForbiddenWithCesuNumber => $byCesuNumber && $presence['civility'],
            self::AddressForbiddenWithCesuNumber => $byCesuNumber && $presence['address'],
            self::EmailForbiddenWithCesuNumber => $byCesuNumber && $presence['email'],
            self::PhoneForbiddenWithCesuNumber => $byCesuNumber && $presence['phone'],
            self::CivilityRequired => !$byCesuNumber && !$presence['civility'],
            self::AddressRequired => !$byCesuNumber && !$presence['address'],
            self::IdentityRequiredWithNir => $kind === EmployeeReferenceKind::Nir && !$presence['identity'],
        };
    }

    /**
     * Path of the offending field, relative to the employee.
     */
    public function path(): string
    {
        return match ($this) {
            self::CivilityForbiddenWithCesuNumber, self::CivilityRequired => 'civility',
            self::AddressForbiddenWithCesuNumber, self::AddressRequired => 'address',
            self::EmailForbiddenWithCesuNumber => 'email',
            self::PhoneForbiddenWithCesuNumber => 'phone',
            self::IdentityRequiredWithNir => 'identity',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::CivilityForbiddenWithCesuNumber => 'declared_employee.civility_forbidden_with_cesu_number',
            self::AddressForbiddenWithCesuNumber => 'declared_employee.address_forbidden_with_cesu_number',
            self::EmailForbiddenWithCesuNumber => 'declared_employee.email_forbidden_with_cesu_number',
            self::PhoneForbiddenWithCesuNumber => 'declared_employee.phone_forbidden_with_cesu_number',
            self::CivilityRequired => 'declared_employee.civility_required',
            self::AddressRequired => 'declared_employee.address_required',
            self::IdentityRequiredWithNir => 'declared_employee.identity_required_with_nir',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::CivilityForbiddenWithCesuNumber => 'The civility must not be given with an employee Cesu number.',
            self::AddressForbiddenWithCesuNumber => 'The address must not be given with an employee Cesu number.',
            self::EmailForbiddenWithCesuNumber => 'The e-mail must not be given with an employee Cesu number.',
            self::PhoneForbiddenWithCesuNumber => 'The phone number must not be given with an employee Cesu number.',
            self::CivilityRequired => 'The civility is required without an employee Cesu number.',
            self::AddressRequired => 'The address is required without an employee Cesu number.',
            self::IdentityRequiredWithNir => 'The administrative data are required with a NIR.',
        };
    }

    /**
     * @param Presence $presence
     *
     * @return list<self>
     */
    public static function violatedBy(EmployeeReferenceKind $kind, array $presence): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn(self $rule): bool => $rule->isViolatedBy($kind, $presence),
        ));
    }
}
