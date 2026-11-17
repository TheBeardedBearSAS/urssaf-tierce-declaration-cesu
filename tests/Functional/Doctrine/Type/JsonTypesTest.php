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
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\DeclarationResultType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\EmployerStatusType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\MandatePresenceCheckType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\SubmissionErrorsType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandatePresenceCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Conversion scenarios of the JSON column types (reference JsonTypesTest, lot 7 design §3.5): one method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class JsonTypesTest extends TestCase
{
    private const ERRORS_TYPE = 'list<' . SubmissionError::class . '>';

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    /**
     * @return iterable<string, array{string}> case names of {@see self::document()}
     */
    public static function documents(): iterable
    {
        yield 'status with unknown raw codes' => ['status-unknown'];
        yield 'status with nulls' => ['status-nulls'];
        yield 'presence check' => ['check'];
        yield 'errors with and without description' => ['errors'];
        yield 'no error' => ['no-errors'];
        yield 'full result' => ['result-full'];
        yield 'minimal result' => ['result-minimal'];
        yield 'result without contributions' => ['result-no-contributions'];
    }

    /**
     * @return array{string, object|list<SubmissionError>, string, string} type name, value, expected JSON, description of the PHP type
     *
     * @throws InvalidValue
     */
    private static function document(string $case): array
    {
        return match ($case) {
            'status-unknown' => [
                EmployerStatusType::NAME,
                new EmployerStatus(true, 'inconnu-demain', 'actif', true, 'etat-futur', 'raison-future', false),
                '{"registered":true,"onlineAccountState":"inconnu-demain","sepaMandateState":"actif","mandatePresent":true,"cesuPlusState":"etat-futur","cesuPlusBlockingReason":"raison-future","aiciActive":false}',
                EmployerStatus::class,
            ],
            'status-nulls' => [
                EmployerStatusType::NAME,
                new EmployerStatus(false, null, null, false, null, null, true),
                '{"registered":false,"onlineAccountState":null,"sepaMandateState":null,"mandatePresent":false,"cesuPlusState":null,"cesuPlusBlockingReason":null,"aiciActive":true}',
                EmployerStatus::class,
            ],
            'check' => [
                MandatePresenceCheckType::NAME,
                MandatePresenceCheck::of(true, Instant::fromIso8601('2026-11-10T10:00:00Z')),
                '{"mandatePresent":true,"checkedAt":"2026-11-10T10:00:00Z"}',
                MandatePresenceCheck::class,
            ],
            'errors' => [
                SubmissionErrorsType::NAME,
                [SubmissionError::of('ERR_METIER_1', 'First message.', 'First description.'), SubmissionError::of('ERR_METIER_2', 'Second message.', null)],
                '[{"code":"ERR_METIER_1","message":"First message.","description":"First description."},{"code":"ERR_METIER_2","message":"Second message.","description":null}]',
                self::ERRORS_TYPE,
            ],
            'no-errors' => [SubmissionErrorsType::NAME, [], '[]', self::ERRORS_TYPE],
            'result-full' => [
                DeclarationResultType::NAME,
                new DeclarationResult(
                    DeclarationId::fromString('209299542888'),
                    Contributions::of(Money::fromCents(6310), Money::fromCents(2009)),
                    Money::fromCents(500),
                    Money::fromCents(100),
                    Money::fromCents(200),
                    Money::fromCents(300),
                    Money::fromCents(40_000),
                    Money::fromCents(30_000),
                    Money::fromCents(0),
                    Money::fromCents(700),
                ),
                '{"declarationId":"209299542888","contributions":{"employer":6310,"employee":2009},"withholdingTax":500,"employerContributionsDebit":100,"employerWithholdingTaxDebit":200,"employerSalaryDebit":300,"grossSalary":40000,"netTaxableSalary":30000,"salaryTransfer":0,"aiciAmount":700}',
                DeclarationResult::class,
            ],
            'result-minimal' => [
                DeclarationResultType::NAME,
                new DeclarationResult(DeclarationId::fromString('2092995'), null, null, null, null, null, null, null, null, null),
                '{"declarationId":"2092995","contributions":null,"withholdingTax":null,"employerContributionsDebit":null,"employerWithholdingTaxDebit":null,"employerSalaryDebit":null,"grossSalary":null,"netTaxableSalary":null,"salaryTransfer":null,"aiciAmount":null}',
                DeclarationResult::class,
            ],
            default => [
                DeclarationResultType::NAME,
                new DeclarationResult(DeclarationId::fromString('2092995'), null, Money::fromCents(1), null, Money::fromCents(2), null, Money::fromCents(3), null, Money::fromCents(4), null),
                '{"declarationId":"2092995","contributions":null,"withholdingTax":1,"employerContributionsDebit":null,"employerWithholdingTaxDebit":2,"employerSalaryDebit":null,"grossSalary":3,"netTaxableSalary":null,"salaryTransfer":4,"aiciAmount":null}',
                DeclarationResult::class,
            ],
        };
    }

    #[DataProvider('documents')]
    public function testItStoresAnExplicitDocumentAndRebuildsTheValue(string $case): void
    {
        [$name, $value, $json] = self::document($case);
        $type = Type::getType($name);

        static::assertSame($json, $type->convertToDatabaseValue($value, $this->platform));
        static::assertEquals($value, $type->convertToPHPValue($json, $this->platform));
    }

    public function testTheRawCodesOfAStatusSurviveTheRoundTrip(): void
    {
        $type = new EmployerStatusType();
        $status = new EmployerStatus(true, 'inconnu-demain', null, false, 'etat-futur', null, false);

        $json = $type->convertToDatabaseValue($status, $this->platform);
        static::assertIsString($json);
        $read = $type->convertToPHPValue($json, $this->platform);

        static::assertInstanceOf(EmployerStatus::class, $read);
        static::assertSame('inconnu-demain', $read->rawOnlineAccountState());
        static::assertNull($read->onlineAccountState());
        static::assertSame('etat-futur', $read->rawCesuPlusState());
        static::assertTrue($read->equals($status));
    }

    #[DataProvider('documents')]
    public function testNullStaysNull(string $case): void
    {
        [$name] = self::document($case);
        $type = Type::getType($name);

        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
    }

    #[DataProvider('documents')]
    public function testItRefusesAForeignPhpValue(string $case): void
    {
        [$name, , , $expected] = self::document($case);

        try {
            Type::getType($name)->convertToDatabaseValue('invalid', $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . $expected . ', null.', $exception->getMessage());
        }
    }

    #[DataProvider('documents')]
    public function testItRefusesAStoredValueThatIsNotValidJsonObjectOrArray(string $case): void
    {
        [$name] = self::document($case);
        $messages = [];
        foreach ([12, '{not json', '12', '"text"'] as $stored) {
            try {
                Type::getType($name)->convertToPHPValue($stored, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        static::assertStringEndsWith('types: string, null.', $messages[0]);
        static::assertStringContainsString('Expected format "JSON"', $messages[1]);
        static::assertStringContainsString('Expected format "JSON object or array"', $messages[2]);
        static::assertStringContainsString('Expected format "JSON object or array"', $messages[3]);
        static::assertStringNotContainsString('{not json', $messages[1]);
        static::assertStringContainsString('database value "***"', $messages[1]);
        static::assertStringContainsString('database value "***"', $messages[2]);
        static::assertStringContainsString('database value "***"', $messages[3]);
    }

    public function testAJsonDocumentIsDeclaredAsAJsonColumn(): void
    {
        foreach ([EmployerStatusType::NAME, MandatePresenceCheckType::NAME, SubmissionErrorsType::NAME, DeclarationResultType::NAME] as $name) {
            static::assertSame(
                $this->platform->getJsonTypeDeclarationSQL([]),
                Type::getType($name)->getSQLDeclaration([], $this->platform),
            );
        }
    }

    public function testItRefusesAListThatIsNotAListOfSubmissionErrors(): void
    {
        $type = Type::getType(SubmissionErrorsType::NAME);
        $error = SubmissionError::of('ERR_A', 'm', 'd');

        foreach ([['a' => $error], [$error, 'text'], [1 => $error]] as $value) {
            try {
                $type->convertToDatabaseValue($value, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                static::assertStringEndsWith('types: ' . self::ERRORS_TYPE . ', null.', $exception->getMessage());
            }
        }
    }

    public function testStoredContentThatTheDomainRefusesIsReportedWithoutEchoingIt(): void
    {
        $cases = [
            MandatePresenceCheckType::NAME => ['{"mandatePresent":true,"checkedAt":"SECRET-instant"}', 'Instant'],
            SubmissionErrorsType::NAME => ['[{"code":"","message":"m","description":"SECRET-description"}]', 'SubmissionError'],
            DeclarationResultType::NAME => ['{"declarationId":"","contributions":null,"grossSalary":424242}', 'DeclarationId'],
        ];
        foreach ($cases as $name => [$stored]) {
            try {
                Type::getType($name)->convertToPHPValue($stored, $this->platform);
                static::fail('Expected a conversion failure for ' . $name);
            } catch (ConversionException $exception) {
                static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
                static::assertStringContainsString('database value "***"', $exception->getMessage());
                static::assertStringNotContainsString('SECRET', $exception->getMessage());
                static::assertStringNotContainsString('424242', $exception->getMessage());
            }
        }
    }

    public function testNegativeCentsAreRefused(): void
    {
        $stored = '{"declarationId":"2092995","contributions":{"employer":-1,"employee":0},"withholdingTax":null,"employerContributionsDebit":null,"employerWithholdingTaxDebit":null,"employerSalaryDebit":null,"grossSalary":null,"netTaxableSalary":null,"salaryTransfer":null,"aiciAmount":null}';

        try {
            Type::getType(DeclarationResultType::NAME)->convertToPHPValue($stored, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
            static::assertStringContainsString('Expected format "' . DeclarationResult::class . '"', $exception->getMessage());
        }
    }
}
