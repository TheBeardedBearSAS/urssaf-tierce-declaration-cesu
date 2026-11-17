<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeIdentityDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;

/**
 * Invalid employee reference drafts with the violations expected for each mode.
 */
final class InvalidEmployeeReferenceDrafts
{
    private const CESU = '00000123456789';

    /**
     * @return iterable<string, array{EmployeeReferenceFactory, EmployeeReferenceDraft, list<string>}>
     */
    public static function provideInvalidLookupDrafts(): iterable
    {
        $identity = EmployeeSamples::identityDraft();
        $abroad = new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', 'Lyon', null, '212');

        yield 'lookup: nothing' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, null), [
            'error identity.birthName birth_name.required',
            'error identity.firstName first_name.required',
            'error identity.birthCountry birth_country.required',
            'error identity.birthDate employee_identity.birth_date_required',
            'error identity.birthCity employee_identity.birth_city_required',
        ]];
        yield 'lookup: invalid cesu number' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft('123', null, null), [
            'error cesuNumber cesu_number.invalid_format',
        ]];
        yield 'lookup: invalid nir' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, '123', null), [
            'error nir nir.invalid_format',
        ]];
        yield 'lookup: cesu number and nir' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(self::CESU, EmployeeSamples::NIR, null), [
            'error nir employee_reference.cesu_number_and_nir_exclusive',
        ]];
        yield 'lookup: cesu number and nir, both invalid' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft('1', '2', null), [
            'error cesuNumber cesu_number.invalid_format',
            'error nir nir.invalid_format',
            'error nir employee_reference.cesu_number_and_nir_exclusive',
        ]];
        yield 'lookup: cesu number with identity' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(self::CESU, null, $identity), [
            'error identity employee_reference.identity_forbidden_with_cesu_number',
        ]];
        yield 'lookup: invalid cesu number with identity' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft('1', null, $identity), [
            'error cesuNumber cesu_number.invalid_format',
            'error identity employee_reference.identity_forbidden_with_cesu_number',
        ]];
        yield 'lookup: identity without birth department in France' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', 'Lyon', null, '100')), [
            'error identity.birthDepartment employee_identity.birth_department_required',
        ]];
        yield 'lookup: identity without birth date nor city abroad' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', null, null, null, '212')), [
            'error identity.birthDate employee_identity.birth_date_required',
            'error identity.birthCity employee_identity.birth_city_required',
        ]];
        yield 'lookup: identity with every field invalid' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('M4rtin', 'D4rand', 'Jul1e', '1990-13-45', 'Ly0n', '9 9', '1 2')), [
            'error identity.birthName birth_name.invalid_characters',
            'error identity.usageName usage_name.invalid_characters',
            'error identity.firstName first_name.invalid_characters',
            'error identity.birthCountry country_code.invalid_characters',
            'error identity.birthDepartment birth_department.invalid_characters',
            'error identity.birthDate calendar_date.invalid_date',
            'error identity.birthCity birth_city.invalid_characters',
        ]];
        yield 'lookup: identity with an invalid birth date only' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', 'x', 'Lyon', null, '212')), [
            'error identity.birthDate calendar_date.invalid_format',
        ]];
        yield 'lookup: identity with an invalid usage name only' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', '1', 'Julie', '1990-05-12', 'Lyon', null, '212')), [
            'error identity.usageName usage_name.invalid_characters',
        ]];
        yield 'lookup: identity with an invalid birth department only' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', 'Lyon', '9 9', '212')), [
            'error identity.birthDepartment birth_department.invalid_characters',
        ]];
        yield 'lookup: identity with an invalid birth city only' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', '1', null, '212')), [
            'error identity.birthCity birth_city.invalid_characters',
        ]];
        yield 'lookup: identity with an invalid first name only' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, '1', '1990-05-12', 'Lyon', null, '212')), [
            'error identity.firstName first_name.invalid_characters',
        ]];
        yield 'lookup: identity with an invalid birth name only' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('1', null, 'Julie', '1990-05-12', 'Lyon', null, '212')), [
            'error identity.birthName birth_name.invalid_characters',
        ]];
        yield 'lookup: identity with an invalid birth country only' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', 'Lyon', null, '1234')), [
            'error identity.birthCountry country_code.too_long',
        ]];
        yield 'lookup: valid identity abroad (control)' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, null, $abroad), []];
        yield 'lookup: nir with identity' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, EmployeeSamples::NIR, $identity), [
            'error identity employee_reference.identity_forbidden_with_nir',
        ]];
        yield 'lookup: invalid nir with identity' => [EmployeeReferenceFactory::forLookup(), new EmployeeReferenceDraft(null, '1', $identity), [
            'error nir nir.invalid_format',
            'error identity employee_reference.identity_forbidden_with_nir',
        ]];
    }

    /**
     * @return iterable<string, array{EmployeeReferenceFactory, EmployeeReferenceDraft, list<string>}>
     */
    public static function provideInvalidDeclarationDrafts(): iterable
    {
        $identity = EmployeeSamples::identityDraft();
        $abroad = new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', 'Lyon', null, '212');

        yield 'declaration: nothing' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, null), [
            'error identity.birthName birth_name.required',
            'error identity.firstName first_name.required',
            'error identity.birthCountry birth_country.required',
            'error identity.birthDate employee_identity.birth_date_required',
            'error identity.birthCity employee_identity.birth_city_required',
        ]];
        yield 'declaration: invalid cesu number' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft('123', null, null), [
            'error cesuNumber cesu_number.invalid_format',
        ]];
        yield 'declaration: invalid nir' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, '123', null), [
            'error nir nir.invalid_format',
        ]];
        yield 'declaration: cesu number and nir' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(self::CESU, EmployeeSamples::NIR, null), [
            'error nir employee_reference.cesu_number_and_nir_exclusive',
        ]];
        yield 'declaration: cesu number and nir, both invalid' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft('1', '2', null), [
            'error cesuNumber cesu_number.invalid_format',
            'error nir nir.invalid_format',
            'error nir employee_reference.cesu_number_and_nir_exclusive',
        ]];
        yield 'declaration: cesu number with identity' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(self::CESU, null, $identity), [
            'error identity employee_reference.identity_forbidden_with_cesu_number',
        ]];
        yield 'declaration: invalid cesu number with identity' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft('1', null, $identity), [
            'error cesuNumber cesu_number.invalid_format',
            'error identity employee_reference.identity_forbidden_with_cesu_number',
        ]];
        yield 'declaration: identity without birth department in France' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', 'Lyon', null, '100')), [
            'error identity.birthDepartment employee_identity.birth_department_required',
        ]];
        yield 'declaration: identity without birth date nor city abroad' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', null, null, null, '212')), [
            'error identity.birthDate employee_identity.birth_date_required',
            'error identity.birthCity employee_identity.birth_city_required',
        ]];
        yield 'declaration: identity with every field invalid' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('M4rtin', 'D4rand', 'Jul1e', '1990-13-45', 'Ly0n', '9 9', '1 2')), [
            'error identity.birthName birth_name.invalid_characters',
            'error identity.usageName usage_name.invalid_characters',
            'error identity.firstName first_name.invalid_characters',
            'error identity.birthCountry country_code.invalid_characters',
            'error identity.birthDepartment birth_department.invalid_characters',
            'error identity.birthDate calendar_date.invalid_date',
            'error identity.birthCity birth_city.invalid_characters',
        ]];
        yield 'declaration: identity with an invalid birth date only' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', 'x', 'Lyon', null, '212')), [
            'error identity.birthDate calendar_date.invalid_format',
        ]];
        yield 'declaration: identity with an invalid usage name only' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', '1', 'Julie', '1990-05-12', 'Lyon', null, '212')), [
            'error identity.usageName usage_name.invalid_characters',
        ]];
        yield 'declaration: identity with an invalid birth department only' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', 'Lyon', '9 9', '212')), [
            'error identity.birthDepartment birth_department.invalid_characters',
        ]];
        yield 'declaration: identity with an invalid birth city only' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', '1', null, '212')), [
            'error identity.birthCity birth_city.invalid_characters',
        ]];
        yield 'declaration: identity with an invalid first name only' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, '1', '1990-05-12', 'Lyon', null, '212')), [
            'error identity.firstName first_name.invalid_characters',
        ]];
        yield 'declaration: identity with an invalid birth name only' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('1', null, 'Julie', '1990-05-12', 'Lyon', null, '212')), [
            'error identity.birthName birth_name.invalid_characters',
        ]];
        yield 'declaration: identity with an invalid birth country only' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', 'Lyon', null, '1234')), [
            'error identity.birthCountry country_code.too_long',
        ]];
        yield 'declaration: valid identity abroad (control)' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, null, $abroad), []];
        yield 'declaration: nir with an invalid identity' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, EmployeeSamples::NIR, new EmployeeIdentityDraft('1', null, null, null, null, null, null)), [
            'error identity.birthName birth_name.invalid_characters',
            'error identity.firstName first_name.required',
            'error identity.birthCountry birth_country.required',
        ]];
        yield 'declaration: invalid nir with a valid identity' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, '1', $identity), [
            'error nir nir.invalid_format',
        ]];
        yield 'declaration: invalid nir with an invalid identity' => [EmployeeReferenceFactory::forDeclaration(), new EmployeeReferenceDraft(null, '1', new EmployeeIdentityDraft('1', null, 'Julie', null, null, null, '212')), [
            'error nir nir.invalid_format',
            'error identity.birthName birth_name.invalid_characters',
        ]];
    }
}
