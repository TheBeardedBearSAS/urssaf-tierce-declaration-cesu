<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\BirthDateType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\CalendarDateType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\CesuNumberType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\DeclarationFingerprintType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\DeclarationIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\DeclarationRecordIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\EmployerAccountIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\MandateIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationFingerprint;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use Throwable;

/**
 * Conversion scenarios of the string-backed and date column types (reference StringValueTypesTest, lot 7 design §3.5).
 */
final class StringValueTypesTest extends TestCase
{
    private const FINGERPRINT = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    /**
     * @return iterable<string, array{string, string, string}> type name, stored value, an invalid stored value
     */
    public static function types(): iterable
    {
        yield 'tenant id' => [TenantIdType::NAME, 'tenant-1', '   '];
        yield 'external reference' => [ExternalReferenceType::NAME, 'employer-1', '   '];
        yield 'employer account id' => [EmployerAccountIdType::NAME, 'account-1', '   '];
        yield 'mandate id' => [MandateIdType::NAME, 'mandate-1', '   '];
        yield 'declaration record id' => [DeclarationRecordIdType::NAME, 'record-1', '   '];
        yield 'declaration id' => [DeclarationIdType::NAME, '209299542888', '   '];
        yield 'Cesu number' => [CesuNumberType::NAME, 'Z0003752390009', 'Z000375239000'];
        yield 'declaration fingerprint' => [DeclarationFingerprintType::NAME, self::FINGERPRINT, 'not-a-fingerprint'];
        yield 'calendar date' => [CalendarDateType::NAME, '2026-10-31', '2026-13-45'];
        yield 'birth date' => [BirthDateType::NAME, '1975-01-30', '1975-13-45'];
    }

    #[DataProvider('types')]
    public function testItStoresTheValueAndRebuildsTheValueObject(string $name, string $stored): void
    {
        $type = Type::getType($name);
        $value = self::valueObject($name, $stored);

        static::assertSame($stored, $type->convertToDatabaseValue($value, $this->platform));
        static::assertEquals($value, $type->convertToPHPValue($stored, $this->platform));
    }

    #[DataProvider('types')]
    public function testNullStaysNull(string $name): void
    {
        $type = Type::getType($name);

        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
    }

    #[DataProvider('types')]
    public function testItRefusesAForeignPhpValue(string $name, string $stored): void
    {
        $foreign = new class implements Stringable {
            public function __toString(): string
            {
                return 'foreign';
            }
        };

        try {
            Type::getType($name)->convertToDatabaseValue($foreign, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . self::valueObject($name, $stored)::class . ', null.', $exception->getMessage());
        }
    }

    #[DataProvider('types')]
    public function testItRefusesAStoredValueThatIsNotAString(string $name): void
    {
        try {
            Type::getType($name)->convertToPHPValue(12, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: string, null.', $exception->getMessage());
        }
    }

    #[DataProvider('types')]
    public function testItReportsAStoredValueTheValueObjectRefuses(string $name, string $stored, string $invalid): void
    {
        try {
            Type::getType($name)->convertToPHPValue($invalid, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringContainsString(Type::getType($name)::class, $exception->getMessage());
            static::assertInstanceOf(Throwable::class, $exception->getPrevious());
            static::assertSame(
                $name !== BirthDateType::NAME,
                str_contains($exception->getMessage(), $invalid),
            );
        }
    }

    public function testTheBirthDateIsNeverQuotedInAConversionError(): void
    {
        try {
            Type::getType(BirthDateType::NAME)->convertToPHPValue('1975-13-45', $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringContainsString('database value "***"', $exception->getMessage());
            static::assertStringNotContainsString('1975', $exception->getMessage());
        }

        try {
            Type::getType(BirthDateType::NAME)->convertToDatabaseValue('1975-01-30', $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringContainsString('***', $exception->getMessage());
            static::assertStringNotContainsString('1975', $exception->getMessage());
        }

        try {
            Type::getType(BirthDateType::NAME)->convertToPHPValue(1_975, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringContainsString('***', $exception->getMessage());
            static::assertStringNotContainsString('1975', $exception->getMessage());
        }
    }

    public function testStringTypesUseTheColumnLengthAndDatesUseTheDateType(): void
    {
        static::assertSame('VARCHAR(64)', Type::getType(TenantIdType::NAME)->getSQLDeclaration(['length' => 64], $this->platform));
        static::assertSame('VARCHAR(14)', Type::getType(CesuNumberType::NAME)->getSQLDeclaration(['length' => 14], $this->platform));
        static::assertSame('DATE', Type::getType(CalendarDateType::NAME)->getSQLDeclaration([], $this->platform));
        static::assertSame('DATE', Type::getType(BirthDateType::NAME)->getSQLDeclaration([], $this->platform));
    }

    /**
     * @throws InvalidValue
     */
    private static function valueObject(string $typeName, string $stored): object
    {
        return match ($typeName) {
            TenantIdType::NAME => TenantId::fromString($stored),
            ExternalReferenceType::NAME => ExternalReference::fromString($stored),
            EmployerAccountIdType::NAME => EmployerAccountId::fromString($stored),
            MandateIdType::NAME => MandateId::fromString($stored),
            DeclarationRecordIdType::NAME => DeclarationRecordId::fromString($stored),
            DeclarationIdType::NAME => DeclarationId::fromString($stored),
            CesuNumberType::NAME => CesuNumber::fromString($stored),
            DeclarationFingerprintType::NAME => DeclarationFingerprint::fromString($stored),
            default => CalendarDate::fromString($stored),
        };
    }
}
