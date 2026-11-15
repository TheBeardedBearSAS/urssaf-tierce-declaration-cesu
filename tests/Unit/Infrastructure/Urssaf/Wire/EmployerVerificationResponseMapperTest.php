<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\CesuPlusState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\SepaMandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployerVerificationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class EmployerVerificationResponseMapperTest extends TestCase
{
    public function testRepairedPdfExample(): void
    {
        $status = (new EmployerVerificationResponseMapper())->map(Fixtures::payload('verify_employer_response'));

        static::assertTrue($status->registered());
        static::assertSame(OnlineAccountState::Absent, $status->onlineAccountState());
        static::assertSame(SepaMandateState::Absent, $status->sepaMandateState());
        static::assertFalse($status->mandatePresent());
        static::assertSame(CesuPlusState::Active, $status->cesuPlusState());
        static::assertSame('activé', $status->rawCesuPlusState());
        static::assertNull($status->cesuPlusBlockingReason());
        static::assertTrue($status->aiciActive());
    }

    public function testAssembledSwaggerExample(): void
    {
        $status = (new EmployerVerificationResponseMapper())->map(Fixtures::payload('verify_employer_response_assembled'));

        static::assertTrue($status->registered());
        static::assertSame(OnlineAccountState::Active, $status->onlineAccountState());
        static::assertSame(SepaMandateState::Active, $status->sepaMandateState());
        static::assertTrue($status->mandatePresent());
        static::assertSame(CesuPlusState::Active, $status->cesuPlusState());
        static::assertSame('C00', $status->cesuPlusBlockingReason());
        static::assertTrue($status->aiciActive());
    }

    public function testAnUnregisteredEmployerComesWithoutTheOtherFields(): void
    {
        $status = (new EmployerVerificationResponseMapper())->map(JsonDecoder::decode('{"inscrit":false}'));

        static::assertFalse($status->registered());
        static::assertNull($status->rawOnlineAccountState());
        static::assertNull($status->rawSepaMandateState());
        static::assertFalse($status->mandatePresent());
        static::assertNull($status->rawCesuPlusState());
        static::assertNull($status->cesuPlusBlockingReason());
        static::assertFalse($status->aiciActive());
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function optionalBooleans(): iterable
    {
        yield 'absent' => ['{"inscrit":true}', false, false];
        yield 'null' => ['{"inscrit":true,"mandatPresent":null,"etatAICI":null}', false, false];
        yield 'true' => ['{"inscrit":true,"mandatPresent":true,"etatAICI":true}', true, true];
        yield 'false' => ['{"inscrit":true,"mandatPresent":false,"etatAICI":false}', false, false];
        yield 'mandate only' => ['{"inscrit":true,"mandatPresent":true}', true, false];
        yield 'aici only' => ['{"inscrit":true,"etatAICI":true}', false, true];
    }

    #[DataProvider('optionalBooleans')]
    public function testOptionalBooleansReadAsFalseWhenAbsent(string $body, bool $mandate, bool $aici): void
    {
        $status = (new EmployerVerificationResponseMapper())->map(JsonDecoder::decode($body));

        static::assertSame($mandate, $status->mandatePresent());
        static::assertSame($aici, $status->aiciActive());
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function blockingReasons(): iterable
    {
        yield 'both casings, the Swagger one wins' => ['{"inscrit":true,"motifBlocageCesuPlus":"C00","motifblocageCesuPlus":"C01"}', 'C00'];
        yield 'both casings, the PDF one is an empty string' => ['{"inscrit":true,"motifBlocageCesuPlus":"C00","motifblocageCesuPlus":""}', 'C00'];
        yield 'only the PDF casing' => ['{"inscrit":true,"motifblocageCesuPlus":"C01"}', 'C01'];
        yield 'Swagger casing null, PDF casing set' => ['{"inscrit":true,"motifBlocageCesuPlus":null,"motifblocageCesuPlus":"C01"}', 'C01'];
        yield 'only the Swagger casing' => ['{"inscrit":true,"motifBlocageCesuPlus":"C00"}', 'C00'];
        yield 'neither' => ['{"inscrit":true}', null];
        yield 'the string null is absent' => ['{"inscrit":true,"motifblocageCesuPlus":"null"}', null];
    }

    #[DataProvider('blockingReasons')]
    public function testBlockingReasonCasings(string $body, ?string $expected): void
    {
        static::assertSame($expected, (new EmployerVerificationResponseMapper())->map(JsonDecoder::decode($body))->cesuPlusBlockingReason());
    }

    public function testUnknownStatesAreKeptRaw(): void
    {
        $status = (new EmployerVerificationResponseMapper())->map(
            JsonDecoder::decode('{"inscrit":true,"etatCompteEnLigne":"nouveau","etatMandatSepa":"autre","etatCesuPlus":"inconnu"}'),
        );

        static::assertSame('nouveau', $status->rawOnlineAccountState());
        static::assertNull($status->onlineAccountState());
        static::assertSame('autre', $status->rawSepaMandateState());
        static::assertNull($status->sepaMandateState());
        static::assertSame('inconnu', $status->rawCesuPlusState());
        static::assertNull($status->cesuPlusState());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'inscrit missing' => ['{}', 'field "inscrit" is missing or not a boolean'];
        yield 'empty body' => ['', 'field "inscrit" is missing or not a boolean'];
        yield 'inscrit not a boolean' => ['{"inscrit":"true"}', 'field "inscrit" is missing or not a boolean'];
        yield 'etatCompteEnLigne not a string' => ['{"inscrit":true,"etatCompteEnLigne":1}', 'field "etatCompteEnLigne" is not a string'];
        yield 'etatMandatSepa not a string' => ['{"inscrit":true,"etatMandatSepa":true}', 'field "etatMandatSepa" is not a string'];
        yield 'mandatPresent not a boolean' => ['{"inscrit":true,"mandatPresent":"oui"}', 'field "mandatPresent" is not a boolean'];
        yield 'etatCesuPlus not a string' => ['{"inscrit":true,"etatCesuPlus":false}', 'field "etatCesuPlus" is not a string'];
        yield 'motifBlocageCesuPlus not a string' => ['{"inscrit":true,"motifBlocageCesuPlus":3}', 'field "motifBlocageCesuPlus" is not a string'];
        yield 'motifblocageCesuPlus not a string' => ['{"inscrit":true,"motifblocageCesuPlus":3}', 'field "motifblocageCesuPlus" is not a string'];
        yield 'etatAICI not a boolean' => ['{"inscrit":true,"etatAICI":"actif"}', 'field "etatAICI" is not a boolean'];
    }

    #[DataProvider('refusals')]
    public function testRefusals(string $body, string $reason): void
    {
        try {
            (new EmployerVerificationResponseMapper())->map(JsonDecoder::decode($body));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame('Unexpected URSSAF response: ' . $reason . '.', $exception->getMessage());
        }
    }
}
