<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployeeVerificationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class EmployeeVerificationResponseMapperTest extends TestCase
{
    public function testOfficialExample(): void
    {
        $result = (new EmployeeVerificationResponseMapper())->map(Fixtures::payload('verify_employee_response'));

        static::assertTrue($result->registered());
        static::assertSame('Z0003752390009', $result->cesuNumber()?->value());
    }

    public function testTheDocumentedLeadingSpaceIsAccepted(): void
    {
        $result = (new EmployeeVerificationResponseMapper())->map(JsonDecoder::decode('{"inscrit":true,"numeroCesu":" Z0003752390009 "}'));

        static::assertSame('Z0003752390009', $result->cesuNumber()?->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function withoutCesuNumber(): iterable
    {
        yield 'missing' => ['{"inscrit":false}'];
        yield 'null' => ['{"inscrit":false,"numeroCesu":null}'];
        yield 'empty' => ['{"inscrit":false,"numeroCesu":""}'];
        yield 'blank' => ['{"inscrit":false,"numeroCesu":"   "}'];
    }

    #[DataProvider('withoutCesuNumber')]
    public function testANotRegisteredEmployeeWithoutNumber(string $body): void
    {
        $result = (new EmployeeVerificationResponseMapper())->map(JsonDecoder::decode($body));

        static::assertFalse($result->registered());
        static::assertNull($result->cesuNumber());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'inscrit missing' => ['{"numeroCesu":"Z0003752390009"}', 'field "inscrit" is missing or not a boolean'];
        yield 'inscrit not a boolean' => ['{"inscrit":"oui"}', 'field "inscrit" is missing or not a boolean'];
        yield 'numeroCesu not a string' => ['{"inscrit":true,"numeroCesu":12}', 'field "numeroCesu" is not a string'];
        yield 'numeroCesu invalid' => ['{"inscrit":true,"numeroCesu":"Z000"}', 'field "numeroCesu" is not valid'];
    }

    #[DataProvider('refusals')]
    public function testRefusals(string $body, string $reason): void
    {
        try {
            (new EmployeeVerificationResponseMapper())->map(JsonDecoder::decode($body));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame('Unexpected URSSAF response: ' . $reason . '.', $exception->getMessage());
        }
    }
}
