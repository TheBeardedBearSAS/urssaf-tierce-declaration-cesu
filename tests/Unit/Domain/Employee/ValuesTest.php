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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\BirthDepartment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class ValuesTest extends TestCase
{
    public function testEmploymentKinds(): void
    {
        static::assertSame(EmploymentKind::HomeEmployment, EmploymentKind::fromCode('PE'));
        static::assertSame(EmploymentKind::FosterCarer, EmploymentKind::fromCode('AF'));
        static::assertFalse(EmploymentKind::HomeEmployment->isFosterCarer());
        static::assertTrue(EmploymentKind::FosterCarer->isFosterCarer());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownEmploymentKinds(): iterable
    {
        yield 'empty' => [''];
        yield 'lower case' => ['pe'];
        yield 'other' => ['XX'];
        yield 'padded' => ['PE '];
    }

    #[DataProvider('provideUnknownEmploymentKinds')]
    public function testUnknownEmploymentKinds(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('employment_kind'));

        EmploymentKind::fromCode($code);
    }

    public function testReferenceKinds(): void
    {
        static::assertSame('cesu_number', EmployeeReferenceKind::CesuNumber->value);
        static::assertSame('nir', EmployeeReferenceKind::Nir->value);
        static::assertSame('identity', EmployeeReferenceKind::Identity->value);
    }

    public function testNir(): void
    {
        $nir = Nir::fromString('269054958815780');

        static::assertSame('269054958815780', $nir->value());
        static::assertTrue($nir->equals(Nir::fromString('269054958815780')));
        static::assertFalse($nir->equals(Nir::fromString('269054958815781')));
        static::assertSame(['value' => '***'], $nir->__debugInfo());
        static::assertStringNotContainsString('2690549', print_r($nir, true));
        static::assertSame('2A9054958815780', Nir::fromString('2A9054958815780')->value());
        static::assertSame('éééééééééééééé1', Nir::fromString('éééééééééééééé1')->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidNirs(): iterable
    {
        yield 'empty' => [''];
        yield 'fourteen characters' => ['26905495881578'];
        yield 'sixteen characters' => ['2690549588157801'];
        yield 'inner space' => ['26905 4958815780'];
        yield 'leading space' => [' 26905495881578'];
        yield 'trailing space' => ['26905495881578 '];
        yield 'trailing newline' => ["26905495881578\n"];
        yield 'leading newline' => ["\n26905495881578"];
        yield 'fourteen multibyte characters' => [str_repeat('é', 14)];
        yield 'seven multibyte characters' => [str_repeat('é', 7) . '1'];
    }

    #[DataProvider('provideInvalidNirs')]
    public function testInvalidNirs(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('nir', '15 characters'));

        Nir::fromString($value);
    }

    public function testBirthDepartment(): void
    {
        static::assertSame('972', BirthDepartment::fromString('972')->value());
        static::assertSame('07', BirthDepartment::fromString('07')->value());
        static::assertSame('2A', BirthDepartment::fromString('2A')->value());
        static::assertSame('ééé', BirthDepartment::fromString('ééé')->value());
        static::assertTrue(BirthDepartment::fromString('07')->equals(BirthDepartment::fromString('07')));
        static::assertFalse(BirthDepartment::fromString('07')->equals(BirthDepartment::fromString('08')));
        static::assertSame(['value' => '***'], BirthDepartment::fromString('07')->__debugInfo());
    }

    /**
     * @return iterable<string, array{string, InvalidValue}>
     */
    public static function provideInvalidDepartments(): iterable
    {
        yield 'empty' => ['', InvalidValue::empty('birth_department')];
        yield 'blank' => ["  \n", InvalidValue::empty('birth_department')];
        yield 'inner space' => ['9 2', InvalidValue::invalidCharacters('birth_department')];
        yield 'leading space' => [' 92', InvalidValue::invalidCharacters('birth_department')];
        yield 'trailing newline' => ["92\n", InvalidValue::invalidCharacters('birth_department')];
        yield 'four characters' => ['9720', InvalidValue::tooLong('birth_department', 3)];
        yield 'four multibyte characters' => ['éééé', InvalidValue::tooLong('birth_department', 3)];
        yield 'invalid and too long' => ["9 7 2 0", InvalidValue::invalidCharacters('birth_department')];
    }

    #[DataProvider('provideInvalidDepartments')]
    public function testInvalidDepartments(string $value, InvalidValue $expected): void
    {
        $this->expectExceptionObject($expected);

        BirthDepartment::fromString($value);
    }
}
