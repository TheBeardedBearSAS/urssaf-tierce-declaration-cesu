<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\DeclarationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class DeclarationResponseMapperTest extends TestCase
{
    private const AMOUNT_KEYS = [
        'montantPAS',
        'montantPrelevementPECotisations',
        'montantPrelevementPEPAS',
        'montantPrelevementPESalaire',
        'montantSalaireBrut',
        'montantSalaireNetImposable',
        'montantVirementSalaire',
        'montantAICI',
    ];

    public function testAssembledFixture(): void
    {
        $result = (new DeclarationResponseMapper())->map(Fixtures::payload('declare_response_assembled'));

        static::assertSame('209299542888', $result->declarationId()->value());
        $contributions = $result->contributions();
        static::assertNotNull($contributions);
        static::assertSame(6310, $contributions->employerContributions()->cents());
        static::assertSame(2009, $contributions->employeeContributions()->cents());
        static::assertSame(0, $result->withholdingTax()?->cents());
        static::assertSame(0, $result->employerContributionsDebit()?->cents());
        static::assertSame(0, $result->employerWithholdingTaxDebit()?->cents());
        static::assertSame(120_000, $result->employerSalaryDebit()?->cents());
        static::assertSame(140_000, $result->grossSalary()?->cents());
        static::assertSame(135_000, $result->netTaxableSalary()?->cents());
        static::assertSame(30_000, $result->salaryTransfer()?->cents());
        static::assertSame(35_000, $result->aiciAmount()?->cents());
    }

    public function testOnlyTheIdentifierIsRequired(): void
    {
        $result = (new DeclarationResponseMapper())->map(JsonDecoder::decode('{"idDeclaration":"A1"}'));

        static::assertSame('A1', $result->declarationId()->value());
        static::assertNull($result->contributions());
        static::assertNull($result->withholdingTax());
        static::assertNull($result->employerContributionsDebit());
        static::assertNull($result->employerWithholdingTaxDebit());
        static::assertNull($result->employerSalaryDebit());
        static::assertNull($result->grossSalary());
        static::assertNull($result->netTaxableSalary());
        static::assertNull($result->salaryTransfer());
        static::assertNull($result->aiciAmount());
    }

    public function testNullAmountsReadAsAbsent(): void
    {
        $body = '{"idDeclaration":"A1","montantsCotisations":null,' . implode(',', array_map(static fn(string $key): string => '"' . $key . '":null', self::AMOUNT_KEYS)) . '}';

        $result = (new DeclarationResponseMapper())->map(JsonDecoder::decode($body));

        static::assertNull($result->contributions());
        static::assertNull($result->withholdingTax());
        static::assertNull($result->aiciAmount());
    }

    public function testAnIntegerIdentifierIsConvertedToItsDecimalString(): void
    {
        static::assertSame('209299542888', (new DeclarationResponseMapper())->map(JsonDecoder::decode('{"idDeclaration":209299542888}'))->declarationId()->value());
    }

    /**
     * @return iterable<string, array{string, callable(DeclarationResult): ?int}>
     */
    public static function amounts(): iterable
    {
        yield 'montantPAS' => ['montantPAS', static fn(DeclarationResult $r): ?int => $r->withholdingTax()?->cents()];
        yield 'montantPrelevementPECotisations' => ['montantPrelevementPECotisations', static fn(DeclarationResult $r): ?int => $r->employerContributionsDebit()?->cents()];
        yield 'montantPrelevementPEPAS' => ['montantPrelevementPEPAS', static fn(DeclarationResult $r): ?int => $r->employerWithholdingTaxDebit()?->cents()];
        yield 'montantPrelevementPESalaire' => ['montantPrelevementPESalaire', static fn(DeclarationResult $r): ?int => $r->employerSalaryDebit()?->cents()];
        yield 'montantSalaireBrut' => ['montantSalaireBrut', static fn(DeclarationResult $r): ?int => $r->grossSalary()?->cents()];
        yield 'montantSalaireNetImposable' => ['montantSalaireNetImposable', static fn(DeclarationResult $r): ?int => $r->netTaxableSalary()?->cents()];
        yield 'montantVirementSalaire' => ['montantVirementSalaire', static fn(DeclarationResult $r): ?int => $r->salaryTransfer()?->cents()];
        yield 'montantAICI' => ['montantAICI', static fn(DeclarationResult $r): ?int => $r->aiciAmount()?->cents()];
    }

    /**
     * @param callable(DeclarationResult): ?int $read
     */
    #[DataProvider('amounts')]
    public function testEachAmountIsReadFromItsOwnKeyAndOnlyWhenPresent(string $key, callable $read): void
    {
        $mapper = new DeclarationResponseMapper();

        $with = $mapper->map(JsonDecoder::decode('{"idDeclaration":"A1","' . $key . '":12.3}'));
        $without = $mapper->map(JsonDecoder::decode('{"idDeclaration":"A1"}'));

        static::assertSame(1230, $read($with));
        static::assertNull($read($without));

        foreach (self::AMOUNT_KEYS as $other) {
            if ($other === $key) {
                continue;
            }

            static::assertNull($read($mapper->map(JsonDecoder::decode('{"idDeclaration":"A1","' . $other . '":1}'))), $other);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'empty body' => ['', 'field "idDeclaration" is missing or not an identifier'];
        yield 'idDeclaration missing' => ['{}', 'field "idDeclaration" is missing or not an identifier'];
        yield 'idDeclaration empty' => ['{"idDeclaration":""}', 'field "idDeclaration" is missing or not an identifier'];
        yield 'idDeclaration boolean' => ['{"idDeclaration":true}', 'field "idDeclaration" is missing or not an identifier'];
        yield 'idDeclaration too long' => ['{"idDeclaration":"' . str_repeat('1', 65) . '"}', 'field "idDeclaration" is not valid'];
        yield 'montantsCotisations not an object' => ['{"idDeclaration":"A1","montantsCotisations":"x"}', 'field "montantsCotisations" is not an object'];
        yield 'employer contributions missing' => ['{"idDeclaration":"A1","montantsCotisations":{"cotisationsSalariales":1}}', 'field "cotisationsPatronales" is missing or not a number'];
        yield 'employee contributions missing' => ['{"idDeclaration":"A1","montantsCotisations":{"cotisationsPatronales":1}}', 'field "cotisationsSalariales" is missing or not a number'];
        yield 'amount not a number' => ['{"idDeclaration":"A1","montantSalaireBrut":"12"}', 'field "montantSalaireBrut" is not a number'];
        yield 'negative amount' => ['{"idDeclaration":"A1","montantAICI":-0.01}', 'amount is not a valid non-negative amount in euros'];
    }

    #[DataProvider('refusals')]
    public function testRefusals(string $body, string $reason): void
    {
        try {
            (new DeclarationResponseMapper())->map(JsonDecoder::decode($body));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame('Unexpected URSSAF response: ' . $reason . '.', $exception->getMessage());
        }
    }

    public function testTheDomainFailureIsKeptAsPreviousWithoutTheValue(): void
    {
        $value = str_repeat('7', 65);

        try {
            (new DeclarationResponseMapper())->map(JsonDecoder::decode('{"idDeclaration":"' . $value . '"}'));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
            static::assertStringNotContainsString($value, $exception->getMessage());
        }
    }
}
