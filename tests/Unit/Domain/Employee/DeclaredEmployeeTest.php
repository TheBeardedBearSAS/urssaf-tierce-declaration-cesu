<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * One method per rule of the value object.
 *
 * @mago-expect lint:too-many-methods
 */
final class DeclaredEmployeeTest extends TestCase
{
    private static function byCesuNumber(): DeclaredEmployee
    {
        return new DeclaredEmployee(EmployeeSamples::byCesuNumber(), EmploymentKind::HomeEmployment, false, null, null, null, null);
    }

    private static function byNir(): DeclaredEmployee
    {
        return new DeclaredEmployee(
            EmployeeSamples::byNir(),
            EmploymentKind::FosterCarer,
            null,
            Civility::Female,
            EmployerSamples::address(),
            EmailAddress::fromString('julie@example.com'),
            PhoneNumber::fromString('0612345678'),
        );
    }

    public function testAccessorsByCesuNumber(): void
    {
        $employee = self::byCesuNumber();

        static::assertSame(EmploymentKind::HomeEmployment, $employee->employmentKind());
        static::assertFalse($employee->paidLeaveIncluded());
        static::assertNull($employee->civility());
        static::assertNull($employee->address());
        static::assertNull($employee->email());
        static::assertNull($employee->phone());
        static::assertSame('00000123456789', $employee->reference()->cesuNumber()?->value());
    }

    public function testAccessorsByNir(): void
    {
        $employee = self::byNir();

        static::assertSame(EmploymentKind::FosterCarer, $employee->employmentKind());
        static::assertNull($employee->paidLeaveIncluded());
        static::assertSame(Civility::Female, $employee->civility());
        static::assertTrue($employee->address()?->equals(EmployerSamples::address()));
        static::assertSame('julie@example.com', $employee->email()?->value());
        static::assertSame('0612345678', $employee->phone()?->value());
    }

    public function testByIdentity(): void
    {
        $employee = new DeclaredEmployee(EmployeeSamples::byIdentity(), EmploymentKind::HomeEmployment, true, Civility::Male, EmployerSamples::address(), null, null);

        static::assertTrue($employee->paidLeaveIncluded());
    }

    public function testByNirWithoutIdentityIsRefused(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'declared_employee.identity_required_with_nir',
            static fn(): DeclaredEmployee => new DeclaredEmployee(
                EmployeeByNir::of(Nir::fromString(EmployeeSamples::NIR), null),
                EmploymentKind::HomeEmployment,
                null,
                Civility::Male,
                EmployerSamples::address(),
                null,
                null,
            ),
        );

        static::assertSame('The administrative data are required with a NIR.', $exception->getMessage());
    }

    public function testByCesuNumberRefusesTheForbiddenParts(): void
    {
        static::assertInstanceOf(InvalidValue::class, DomainAssertions::assertViolationCode(
            'declared_employee.civility_forbidden_with_cesu_number',
            static fn(): DeclaredEmployee => new DeclaredEmployee(EmployeeSamples::byCesuNumber(), EmploymentKind::HomeEmployment, null, Civility::Male, EmployerSamples::address(), null, null),
        ));
        static::assertInstanceOf(InvalidValue::class, DomainAssertions::assertViolationCode(
            'declared_employee.address_forbidden_with_cesu_number',
            static fn(): DeclaredEmployee => new DeclaredEmployee(EmployeeSamples::byCesuNumber(), EmploymentKind::HomeEmployment, null, null, EmployerSamples::address(), null, null),
        ));
        static::assertInstanceOf(InvalidValue::class, DomainAssertions::assertViolationCode(
            'declared_employee.email_forbidden_with_cesu_number',
            static fn(): DeclaredEmployee => new DeclaredEmployee(EmployeeSamples::byCesuNumber(), EmploymentKind::HomeEmployment, null, null, null, EmailAddress::fromString('a@example.com'), null),
        ));
        static::assertInstanceOf(InvalidValue::class, DomainAssertions::assertViolationCode(
            'declared_employee.phone_forbidden_with_cesu_number',
            static fn(): DeclaredEmployee => new DeclaredEmployee(EmployeeSamples::byCesuNumber(), EmploymentKind::HomeEmployment, null, null, null, null, PhoneNumber::fromString('0612345678')),
        ));
    }

    public function testByIdentityRequiresTheCivilityAndTheAddress(): void
    {
        static::assertInstanceOf(InvalidValue::class, DomainAssertions::assertViolationCode(
            'declared_employee.civility_required',
            static fn(): DeclaredEmployee => new DeclaredEmployee(EmployeeSamples::byIdentity(), EmploymentKind::HomeEmployment, null, null, EmployerSamples::address(), null, null),
        ));
        static::assertInstanceOf(InvalidValue::class, DomainAssertions::assertViolationCode(
            'declared_employee.address_required',
            static fn(): DeclaredEmployee => new DeclaredEmployee(EmployeeSamples::byIdentity(), EmploymentKind::HomeEmployment, null, Civility::Male, null, null, null),
        ));
    }

    public function testTheFirstViolatedRuleWins(): void
    {
        static::assertInstanceOf(InvalidValue::class, DomainAssertions::assertViolationCode(
            'declared_employee.civility_required',
            static fn(): DeclaredEmployee => new DeclaredEmployee(EmployeeSamples::byIdentity(), EmploymentKind::HomeEmployment, null, null, null, null, null),
        ));
    }

    /**
     * @param array{reference?: EmployeeReference, kind?: EmploymentKind, leave?: bool|null, civility?: Civility, address?: Address, email?: EmailAddress|null, phone?: PhoneNumber|null} $changes
     */
    private static function variant(array $changes): DeclaredEmployee
    {
        return new DeclaredEmployee(
            $changes['reference'] ?? EmployeeSamples::byNir(),
            $changes['kind'] ?? EmploymentKind::FosterCarer,
            $changes['leave'] ?? null,
            $changes['civility'] ?? Civility::Female,
            $changes['address'] ?? EmployerSamples::address(),
            \array_key_exists('email', $changes) ? $changes['email'] : EmailAddress::fromString('julie@example.com'),
            \array_key_exists('phone', $changes) ? $changes['phone'] : PhoneNumber::fromString('0612345678'),
        );
    }

    public function testEquality(): void
    {
        $employee = self::byNir();
        static::assertTrue($employee->equals(self::variant([])));
        static::assertFalse($employee->equals(self::variant(['reference' => EmployeeByNir::of(Nir::fromString('269054958815781'), EmployeeSamples::identity())])));
        static::assertFalse($employee->equals(self::variant(['kind' => EmploymentKind::HomeEmployment])));
        static::assertFalse($employee->equals(self::variant(['leave' => true])));
        static::assertFalse($employee->equals(self::variant(['leave' => false])));
        static::assertFalse($employee->equals(self::variant(['civility' => Civility::Male])));
        static::assertFalse($employee->equals(self::variant(['address' => new Address(null, null, null, null, AddressText::complement('Autre'), null, null, PostalCode::fromString('69003'))])));
        static::assertFalse($employee->equals(self::variant(['email' => EmailAddress::fromString('autre@example.com')])));
        static::assertFalse($employee->equals(self::variant(['email' => null])));
        static::assertFalse($employee->equals(self::variant(['phone' => PhoneNumber::fromString('0612345679')])));
        static::assertFalse($employee->equals(self::variant(['phone' => null])));
        static::assertFalse(self::byCesuNumber()->equals($employee));
        static::assertTrue(self::byCesuNumber()->equals(self::byCesuNumber()));
    }
}
