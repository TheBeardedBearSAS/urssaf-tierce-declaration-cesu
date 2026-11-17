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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\DeclaredEmployeeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\DeclaredEmployeeParser;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeIdentityDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;

final class DeclaredEmployeeParserTest extends TestCase
{
    private const CESU = '00000123456789';

    private static function address(): AddressDraft
    {
        return new AddressDraft(12, 'B', 'R', 'De la Paix', null, null, '69383', '69003');
    }

    public function testByCesuNumber(): void
    {
        $collector = new ViolationCollector();

        $employee = (new DeclaredEmployeeParser())->parse(new DeclaredEmployeeDraft(self::CESU, null, null, null, null, null, null, 'PE', false), $collector);

        static::assertSame([], DomainAssertions::summary($collector->result()));
        static::assertNotNull($employee);
        static::assertSame(self::CESU, $employee->reference()->cesuNumber()?->value());
        static::assertSame(EmploymentKind::HomeEmployment, $employee->employmentKind());
        static::assertFalse($employee->paidLeaveIncluded());
        static::assertNull($employee->civility());
    }

    public function testByNirWithTheAdministrativeData(): void
    {
        $collector = new ViolationCollector();

        $employee = (new DeclaredEmployeeParser())->parse(
            new DeclaredEmployeeDraft(null, EmployeeSamples::NIR, EmployeeSamples::identityDraft(), '2', self::address(), 'julie@example.com', '0612345678', 'AF', null),
            $collector,
        );

        static::assertSame([], DomainAssertions::summary($collector->result()));
        static::assertNotNull($employee);
        static::assertInstanceOf(EmployeeByNir::class, $employee->reference());
        static::assertSame(EmploymentKind::FosterCarer, $employee->employmentKind());
        static::assertNull($employee->paidLeaveIncluded());
        static::assertSame(Civility::Female, $employee->civility());
        static::assertSame('69003', $employee->address()?->postalCode()->value());
        static::assertSame('julie@example.com', $employee->email()?->value());
        static::assertSame('0612345678', $employee->phone()?->value());
    }

    public function testByIdentity(): void
    {
        $parser = new DeclaredEmployeeParser();
        $draft = new DeclaredEmployeeDraft(null, null, EmployeeSamples::identityDraft(), '1', self::address(), null, null, 'PE', true);

        $employee = $parser->parse($draft, new ViolationCollector());

        static::assertNotNull($employee);
        static::assertInstanceOf(EmployeeByIdentity::class, $employee->reference());
        static::assertTrue($employee->paidLeaveIncluded());
        static::assertSame([], DomainAssertions::summary($parser->validate($draft)));
    }

    public function testEmptyStringsCountAsMissing(): void
    {
        $employee = (new DeclaredEmployeeParser())->parse(
            new DeclaredEmployeeDraft(self::CESU, '', new EmployeeIdentityDraft('', '', '', '', '', '', ''), '', null, '', '', 'PE', null),
            new ViolationCollector(),
        );

        static::assertNotNull($employee);
    }

    /**
     * @return iterable<string, array{DeclaredEmployeeDraft, list<string>}>
     */
    public static function provideInvalidDrafts(): iterable
    {
        $identity = EmployeeSamples::identityDraft();
        $address = self::address();

        yield 'nothing' => [new DeclaredEmployeeDraft(null, null, null, null, null, null, null, null, null), [
            'error identity.birthName birth_name.required',
            'error identity.firstName first_name.required',
            'error identity.birthCountry birth_country.required',
            'error identity.birthDate employee_identity.birth_date_required',
            'error identity.birthCity employee_identity.birth_city_required',
            'error employmentKind employment_kind.required',
            'error civility declared_employee.civility_required',
            'error address declared_employee.address_required',
        ]];
        yield 'unknown employment kind' => [new DeclaredEmployeeDraft(self::CESU, null, null, null, null, null, null, 'XX', null), [
            'error employmentKind employment_kind.unknown',
        ]];
        yield 'missing employment kind' => [new DeclaredEmployeeDraft(self::CESU, null, null, null, null, null, null, null, null), [
            'error employmentKind employment_kind.required',
        ]];
        yield 'invalid cesu number' => [new DeclaredEmployeeDraft('1', null, null, null, null, null, null, 'PE', null), [
            'error cesuNumber cesu_number.invalid_format',
        ]];
        yield 'cesu number and nir' => [new DeclaredEmployeeDraft(self::CESU, EmployeeSamples::NIR, null, null, null, null, null, 'PE', null), [
            'error nir employee_reference.cesu_number_and_nir_exclusive',
        ]];
        yield 'cesu number with everything forbidden, nothing of it parsed' => [new DeclaredEmployeeDraft(self::CESU, null, null, '9', new AddressDraft(null, null, null, null, null, null, null, null), 'x', 'y', 'PE', null), [
            'error civility declared_employee.civility_forbidden_with_cesu_number',
            'error address declared_employee.address_forbidden_with_cesu_number',
            'error email declared_employee.email_forbidden_with_cesu_number',
            'error phone declared_employee.phone_forbidden_with_cesu_number',
        ]];
        yield 'cesu number with identity' => [new DeclaredEmployeeDraft(self::CESU, null, $identity, null, null, null, null, 'PE', null), [
            'error identity employee_reference.identity_forbidden_with_cesu_number',
        ]];
        yield 'nir alone' => [new DeclaredEmployeeDraft(null, EmployeeSamples::NIR, null, null, null, null, null, 'PE', null), [
            'error civility declared_employee.civility_required',
            'error address declared_employee.address_required',
            'error identity declared_employee.identity_required_with_nir',
        ]];
        yield 'nir without civility' => [new DeclaredEmployeeDraft(null, EmployeeSamples::NIR, $identity, null, $address, null, null, 'PE', null), [
            'error civility declared_employee.civility_required',
        ]];
        yield 'nir without address' => [new DeclaredEmployeeDraft(null, EmployeeSamples::NIR, $identity, '1', null, null, null, 'PE', null), [
            'error address declared_employee.address_required',
        ]];
        yield 'identity without civility' => [new DeclaredEmployeeDraft(null, null, $identity, null, $address, null, null, 'PE', null), [
            'error civility declared_employee.civility_required',
        ]];
        yield 'identity without address' => [new DeclaredEmployeeDraft(null, null, $identity, '1', null, null, null, 'PE', null), [
            'error address declared_employee.address_required',
        ]];
        yield 'invalid civility' => [new DeclaredEmployeeDraft(null, null, $identity, '9', $address, null, null, 'PE', null), [
            'error civility civility.unknown',
        ]];
        yield 'invalid address' => [new DeclaredEmployeeDraft(null, null, $identity, '1', new AddressDraft(null, null, null, null, null, null, null, null), null, null, 'PE', null), [
            'error address address.location_required',
            'error address.postalCode postal_code.required',
        ]];
        yield 'invalid e-mail' => [new DeclaredEmployeeDraft(null, null, $identity, '1', $address, 'x', null, 'PE', null), [
            'error email email_address.invalid_format',
        ]];
        yield 'invalid phone' => [new DeclaredEmployeeDraft(null, null, $identity, '1', $address, null, '1', 'PE', null), [
            'error phone phone_number.invalid_format',
        ]];
        yield 'invalid identity' => [new DeclaredEmployeeDraft(null, EmployeeSamples::NIR, new EmployeeIdentityDraft('1', null, 'Julie', null, null, null, '212'), '1', $address, null, null, 'PE', null), [
            'error identity.birthName birth_name.invalid_characters',
        ]];
        yield 'invalid nir' => [new DeclaredEmployeeDraft(null, '1', $identity, '1', $address, null, null, 'PE', null), [
            'error nir nir.invalid_format',
        ]];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidDrafts')]
    public function testInvalidDrafts(DeclaredEmployeeDraft $draft, array $expected): void
    {
        $parser = new DeclaredEmployeeParser();
        $collector = new ViolationCollector();

        $employee = $parser->parse($draft, $collector);

        static::assertNull($employee);
        static::assertSame($expected, DomainAssertions::summary($collector->result()));
        static::assertSame($expected, DomainAssertions::summary($parser->validate($draft)));
    }

    public function testPreviousErrorsOfTheCollectorAreIgnored(): void
    {
        $parser = new DeclaredEmployeeParser();
        $collector = new ViolationCollector();
        $parser->parse(new DeclaredEmployeeDraft(null, null, null, null, null, null, null, null, null), $collector);

        $employee = $parser->parse(new DeclaredEmployeeDraft(self::CESU, null, null, null, null, null, null, 'PE', null), $collector);

        static::assertNotNull($employee);
    }

    public function testRuleMessages(): void
    {
        $result = (new DeclaredEmployeeParser())->validate(new DeclaredEmployeeDraft(null, EmployeeSamples::NIR, null, null, null, null, null, 'PE', null));

        static::assertSame(
            [
                'The civility is required without an employee Cesu number.',
                'The address is required without an employee Cesu number.',
                'The administrative data are required with a NIR.',
            ],
            array_map(static fn(Violation $violation): string => $violation->message(), $result->errors()),
        );
    }
}
