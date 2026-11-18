<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Contract;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Offline checks of the contract suite's own support code (it never reaches the network and needs no credentials).
 *
 * @mago-expect lint:too-many-methods Each support class and each behaviour is one test method.
 */
#[Group('contract-support')]
final class SandboxSupportTest extends TestCase
{
    private string $reportPath;

    /** @var array{points: null|array<string, array{point: string, state: string, value: mixed, test: string}>, path: null|string, tokenHost: string, baseUriHost: string, writesAllowed: bool} */
    private array $saved;

    protected function setUp(): void
    {
        $this->reportPath = \dirname(__DIR__, 2) . '/var/contract/support-report.json';
        $this->saved = SandboxReport::reset();
        SandboxReport::usePath($this->reportPath);
    }

    protected function tearDown(): void
    {
        SandboxReport::restore($this->saved);
        if (is_file($this->reportPath)) {
            unlink($this->reportPath);
        }
    }

    public function testEnvironmentWithoutVariablesHasNoCredentialsAndNothingConfigured(): void
    {
        $env = SandboxEnvironment::fromArray([]);

        static::assertFalse($env->hasCredentials());
        static::assertNull($env->baseUri());
        static::assertNull($env->tokenUri());
        static::assertNull($env->employer());
        static::assertNull($env->employeeCesuNumber());
        static::assertFalse($env->writesAllowed());
    }

    public function testEnvironmentReadsValidValues(): void
    {
        $env = SandboxEnvironment::fromArray([
            SandboxEnvironment::CLIENT_ID => 'id',
            SandboxEnvironment::CLIENT_SECRET => 'secret',
            SandboxEnvironment::BASE_URI => 'https://sandbox.example.test',
            SandboxEnvironment::TOKEN_URI => 'https://token.example.test/token',
            SandboxEnvironment::EMPLOYER_CESU_NUMBER => SandboxData::fictitiousEmployerCesuNumber(),
            SandboxEnvironment::EMPLOYER_BIRTH_DATE => SandboxData::fictitiousBirthDate(),
            SandboxEnvironment::EMPLOYEE_CESU_NUMBER => SandboxData::fictitiousEmployeeCesuNumber(),
            SandboxEnvironment::ALLOW_WRITES => '1',
        ]);

        static::assertTrue($env->hasCredentials());
        static::assertSame('id', $env->credentials()->clientId());
        static::assertSame('https://sandbox.example.test', $env->baseUri());
        static::assertSame('https://token.example.test/token', $env->tokenUri());
        static::assertSame(SandboxData::fictitiousEmployerCesuNumber(), $env->employer()?->cesuNumber()->value());
        static::assertSame(SandboxData::fictitiousEmployeeCesuNumber(), $env->employeeCesuNumber()?->value());
        static::assertTrue($env->writesAllowed());
        static::assertFalse(SandboxEnvironment::fromArray([SandboxEnvironment::ALLOW_WRITES => 'true'])->writesAllowed());
    }

    public function testEnvironmentRefusesTheProductionHost(): void
    {
        $env = SandboxEnvironment::fromArray([SandboxEnvironment::BASE_URI => 'https://API.urssaf.fr']);

        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::BASE_URI);
        $env->baseUri();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRefusedUris(): iterable
    {
        yield 'production host' => ['https://api.urssaf.fr/api/oauth/v1/token', 'targets the production host'];
        yield 'production host, upper case, trailing dot' => ['https://API.URSSAF.FR./token', 'targets the production host'];
        yield 'http scheme' => ['http://sandbox.example.test/token', 'must be an https URI'];
        yield 'no host' => ['https:token', 'has no host'];
    }

    #[DataProvider('provideRefusedUris')]
    public function testEnvironmentRefusesAnUnsafeTokenUri(string $uri, string $reason): void
    {
        $env = SandboxEnvironment::fromArray([SandboxEnvironment::TOKEN_URI => $uri]);

        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::TOKEN_URI);
        $this->expectExceptionMessage($reason);
        $env->tokenUri();
    }

    public function testEnvironmentRefusesAnHttpBaseUri(): void
    {
        $env = SandboxEnvironment::fromArray([SandboxEnvironment::BASE_URI => 'http://sandbox.example.test']);

        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage('must be an https URI');
        $env->baseUri();
    }

    public function testEnvironmentNamesTheVariableNeverTheValue(): void
    {
        $bad = SandboxEnvironment::fromArray([SandboxEnvironment::EMPLOYEE_CESU_NUMBER => 'secret-value']);
        try {
            $bad->employeeCesuNumber();
            static::fail('A wrong Cesu number must be refused.');
        } catch (SandboxMisconfigured $e) {
            static::assertStringContainsString(SandboxEnvironment::EMPLOYEE_CESU_NUMBER, $e->getMessage());
            static::assertStringNotContainsString('secret-value', $e->getMessage());
        }

        $noDate = SandboxEnvironment::fromArray([SandboxEnvironment::EMPLOYER_CESU_NUMBER => SandboxData::fictitiousEmployerCesuNumber()]);
        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::EMPLOYER_BIRTH_DATE);
        $noDate->employer();
    }

    public function testEnvironmentRefusesAnInvalidBirthDateWithoutEchoingIt(): void
    {
        $env = SandboxEnvironment::fromArray([
            SandboxEnvironment::EMPLOYER_CESU_NUMBER => SandboxData::fictitiousEmployerCesuNumber(),
            SandboxEnvironment::EMPLOYER_BIRTH_DATE => '29/03/1980',
        ]);

        try {
            $env->employer();
            static::fail('A wrong date must be refused.');
        } catch (SandboxMisconfigured $e) {
            static::assertSame('URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE is not a YYYY-MM-DD date.', $e->getMessage());
        }
    }

    public function testProcessEnvironmentIgnoresBlankValues(): void
    {
        static::assertNull(SandboxEnvironment::env('URSSAF_SANDBOX_DEFINITELY_UNSET'));
        putenv('URSSAF_SANDBOX_SUPPORT_TEST=  value ');
        static::assertSame('value', SandboxEnvironment::env('URSSAF_SANDBOX_SUPPORT_TEST'));
        putenv('URSSAF_SANDBOX_SUPPORT_TEST=   ');
        static::assertNull(SandboxEnvironment::env('URSSAF_SANDBOX_SUPPORT_TEST'));
        putenv('URSSAF_SANDBOX_SUPPORT_TEST');
    }

    public function testGatewayIsNullWithoutCredentialsEvenWithAProductionBaseUri(): void
    {
        $env = SandboxEnvironment::fromArray([SandboxEnvironment::BASE_URI => 'https://api.urssaf.fr']);

        static::assertNull(SandboxGateway::fromEnvironment($env));
    }

    public function testGatewayRefusesAProductionTokenUriEagerly(): void
    {
        $env = SandboxEnvironment::fromArray([
            SandboxEnvironment::CLIENT_ID => 'id',
            SandboxEnvironment::CLIENT_SECRET => 'secret',
            SandboxEnvironment::TOKEN_URI => 'https://api.urssaf.fr/token',
        ]);

        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::TOKEN_URI);
        SandboxGateway::fromEnvironment($env);
    }

    public function testGatewayIsWiredWithoutAnyNetworkCall(): void
    {
        $env = SandboxEnvironment::fromArray([
            SandboxEnvironment::CLIENT_ID => 'id',
            SandboxEnvironment::CLIENT_SECRET => 'secret',
            SandboxEnvironment::TOKEN_URI => 'https://token.example.test/token',
            SandboxEnvironment::ALLOW_WRITES => '1',
        ]);

        $sandbox = SandboxGateway::fromEnvironment($env);

        static::assertInstanceOf(SandboxGateway::class, $sandbox);
        static::assertSame('https://token.example.test/token', $sandbox->tokenUriOverride);
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', $sandbox->configuration->tokenUri());
        static::assertSame([], $sandbox->http->exchanges());
        SandboxReport::flush();
        $report = $this->readReport();
        static::assertSame('api-edi.urssaf.fr', $report['baseUriHost']);
        static::assertSame('token.example.test', $report['tokenHost']);
        static::assertTrue($report['writesAllowed']);
    }

    public function testExchangeKeepsTheShapeOnly(): void
    {
        $request = new Request('GET', 'https://host.example.test/tiersdecl/v1/cesu/employeurs/Z0000000000000/verifier?dateNaissance=1980-03-29');
        $body = '{"inscrit":true,"etatCesuPlus":"activé","motifblocageCesuPlus":"null","etatAICI":null,"mandatPresent":false,'
            . '"numeroCesu":"Z0000000000000 ","etatCompteEnLigne":"weird","etatMandatSepa":"actif","montant":1.234,"idDeclaration":12}';
        $response = new Response(200, ['Content-Type' => 'application/json', 'X-Request-Id' => 'abc', 'Retry-After' => '30'], $body);

        $exchange = Exchange::from($request, $response);

        static::assertSame('GET', $exchange->method);
        static::assertSame('host.example.test', $exchange->host);
        static::assertSame('/tiersdecl/v1/cesu/employeurs/{numeroCesu}/verifier', $exchange->pathTemplate);
        static::assertSame(200, $exchange->status);
        static::assertTrue($exchange->isSuccessful());
        static::assertSame(['content-type', 'retry-after', 'x-request-id'], $exchange->headerNames);
        static::assertSame('30', $exchange->retryAfter);
        static::assertSame(Exchange::OBJECT, $exchange->bodyShape);
        static::assertContains('etatCesuPlus', $exchange->bodyKeys);
        static::assertSame(
            ['etatCesuPlus' => 'activé', 'etatCompteEnLigne' => 'other(length=5)', 'etatMandatSepa' => 'actif'],
            $exchange->enumValues,
        );
        static::assertSame(
            ['idDeclaration' => 'integer', 'inscrit' => 'bool', 'mandatPresent' => 'bool', 'etatAICI' => 'null', 'motifblocageCesuPlus' => 'string', 'numeroCesu' => 'string'],
            $exchange->jsonTypes,
        );
        static::assertTrue($exchange->hasMoreThanTwoDecimals);
        static::assertTrue($exchange->motifIsStringNull);
        static::assertSame(['length' => 15, 'trimmedLength' => 14, 'charset' => 'alnum_upper'], $exchange->numeroCesuShape);
        static::assertSame('{"inscrit":true', substr((string) $response->getBody(), 0, 15), 'The body is rewound for the gateway.');
        static::assertStringNotContainsString('Z0000000000000', json_encode($exchange, \JSON_THROW_ON_ERROR));
        static::assertStringNotContainsString('1980', json_encode($exchange, \JSON_THROW_ON_ERROR));
    }

    public function testExchangeBodyShapes(): void
    {
        $request = new Request('POST', 'https://host.example.test/x');
        $shape = static fn(string $body, array $headers = []): Exchange => Exchange::from($request, new Response(400, $headers, $body));

        static::assertSame(Exchange::EMPTY, $shape('')->bodyShape);
        static::assertSame(Exchange::NOT_JSON, $shape('<html>')->bodyShape);
        static::assertSame(Exchange::SCALAR, $shape('"text"')->bodyShape);
        $list = $shape('[{"code":"PARAM_INVALIDE","message":"never kept"},"x"]');
        static::assertSame(Exchange::LIST, $list->bodyShape);
        static::assertSame(['code', 'message'], $list->bodyKeys);
        static::assertSame(['PARAM_INVALIDE'], $list->codes());
        static::assertSame([], $shape('[1,2]')->bodyKeys);
        static::assertSame([], $shape('{"code":"not a code"}')->codes());
        static::assertSame('other(length=10)', $shape('{"code":"not a code"}')->enumValues['code']);
        static::assertSame('CODE_SHAPE', $shape('{"code":"CODE_SHAPE"}')->enumValues['code']);
        static::assertSame([], $shape('{"code":12,"etatCesuPlus":3}')->enumValues);
        static::assertSame('Bearer', $shape('{"token_type":"Bearer"}')->enumValues['token_type']);
        static::assertSame('unparseable', $shape('{}', ['Retry-After' => 'soon'])->retryAfter);
        static::assertSame('Wed, 21 Oct 2026 07:28:00 GMT', $shape('{}', ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'])->retryAfter);
        static::assertNull($shape('{}')->retryAfter);
        static::assertFalse($shape('{"a":1.5,"b":[2.25]}')->hasMoreThanTwoDecimals);
        static::assertTrue($shape('{"a":[{"b":0.333}]}')->hasMoreThanTwoDecimals);
        static::assertFalse($shape('{"motifBlocageCesuPlus":null}')->motifIsStringNull);
        static::assertSame(
            ['numeroCesu' => 'integer', 'expires_in' => 'float'],
            $shape('{"numeroCesu":5,"expires_in":3600.5}')->jsonTypes,
        );
        static::assertSame(['a' => 'array', 'b' => 'object'], array_intersect_key(
            ['a' => 'array', 'b' => 'object'],
            $shape('{"a":[],"b":{}}')->jsonTypes + ['a' => 'array', 'b' => 'object'],
        ));
        static::assertNull($shape('{"numeroCesu":5}')->numeroCesuShape);
        static::assertSame('digits', $shape('{"numeroCesu":"00000000000000"}')->numeroCesuShape['charset'] ?? null);
        static::assertSame('other', $shape('{"numeroCesu":"a-b"}')->numeroCesuShape['charset'] ?? null);
    }

    public function testHttpClientRewritesOnlyTheTokenRequestAndRecordsShapes(): void
    {
        $inner = new class implements ClientInterface {
            /** @var list<RequestInterface> */
            private array $requests = [];

            public function hostAt(int $index): string
            {
                return $this->requests[$index]->getUri()->getHost();
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;

                return str_contains($request->getUri()->getPath(), 'oauth')
                    ? new Response(200, [], '{"access_token":"fake","expires_in":3600}')
                    : new Response(429, ['Retry-After' => '12'], '{"code":"QUOTA_ERROR"}');
            }
        };
        $client = new SandboxHttpClient($inner, 'https://token.example.test/oauth/token');

        $client->sendRequest(new Request('POST', 'https://api-edi.urssaf.fr/api/oauth/v1/token', [], 'client_secret=never-recorded'));
        $mark = $client->mark();
        $client->sendRequest(new Request('GET', 'https://api-edi.urssaf.fr/tiersdecl/v1/cesu/estimation'));
        $probe = $client->probe(new Request('POST', 'https://other.example.test/api/oauth/v1/token'));

        static::assertSame('token.example.test', $inner->hostAt(0));
        static::assertSame('api-edi.urssaf.fr', $inner->hostAt(1));
        static::assertSame('other.example.test', $inner->hostAt(2));
        static::assertCount(3, $client->exchanges());
        static::assertCount(2, $client->since($mark));
        static::assertSame($probe, $client->last());
        static::assertSame(200, $probe->status);

        $points = SandboxReport::points();
        static::assertSame(SandboxReport::OBSERVED, $points['5.15-429']['state']);
        static::assertEquals(
            ['retryAfter' => '12', 'bodyShape' => 'object', 'bodyKeys' => ['code'], 'codes' => ['QUOTA_ERROR']],
            $points['5.15-429']['value'],
        );
        static::assertEquals(['headerNames' => ['retry-after'], 'retryAfterSeen' => true], $points['5.15-headers']['value']);
        static::assertStringNotContainsString('never-recorded', json_encode($client->exchanges(), \JSON_THROW_ON_ERROR));
    }

    public function testHttpClientWithoutOverrideLeavesTheTokenRequestAlone(): void
    {
        $inner = new class implements ClientInterface {
            private ?RequestInterface $request = null;

            public function host(): ?string
            {
                return $this->request?->getUri()->getHost();
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(200, [], '{}');
            }
        };
        $client = new SandboxHttpClient($inner, null);

        static::assertNull($client->last());
        $client->sendRequest(new Request('POST', 'https://api-edi.urssaf.fr/api/oauth/v1/token'));

        static::assertSame('api-edi.urssaf.fr', $inner->host());
        static::assertSame(SandboxReport::NOT_RUN, SandboxReport::points()['5.15-headers']['state']);
    }

    public function testReportPreSeedsEveryPointAndKeepsTheStrongestState(): void
    {
        static::assertCount(\count(SandboxReport::POINTS), SandboxReport::points());
        SandboxReport::record('070-aici', SandboxReport::OBSERVED, 'active', 'a');
        SandboxReport::record('070-aici', SandboxReport::NOT_OBSERVED, null, 'b');
        SandboxReport::record('5.6-accord', SandboxReport::NOT_OBSERVED, 'x', 'c');
        SandboxReport::record('5.6-accord', SandboxReport::OBSERVED, 'boolean', 'd');
        SandboxReport::describeRun('host.example.test', 'token.example.test', false);
        SandboxReport::flush();

        $report = $this->readReport();
        static::assertSame(1, $report['schema']);
        static::assertSame('2.0.2', $report['specVersion']);
        static::assertSame('host.example.test', $report['baseUriHost']);
        static::assertFalse($report['writesAllowed']);
        // @mago-expect analysis:mixed-assignment The decoded report is untyped: it is asserted to be an array right below.
        $points = $report['points'];
        static::assertIsArray($points);
        static::assertSame(['state' => 'observed', 'value' => 'active', 'test' => 'a'], $points['070-aici']);
        static::assertSame(['state' => 'observed', 'value' => 'boolean', 'test' => 'd'], $points['5.6-accord']);
        static::assertSame(['state' => 'not_run', 'value' => null, 'test' => ''], $points['5.1-etatCesuPlus']);
        static::assertCount(\count(SandboxReport::POINTS), $points);
    }

    public function testFictitiousDataBuildsValidDomainObjects(): void
    {
        static::assertSame('Z0000000000000', SandboxData::fictitiousEmployer()->cesuNumber()->value());
        static::assertNotNull(SandboxData::officialEstimateRequest()->hourlyIncome());
        static::assertNotEquals(SandboxData::newEmployer(), SandboxData::newEmployer());

        $employer = SandboxData::fictitiousEmployer();
        static::assertSame($employer->cesuNumber()->value(), SandboxData::aiciRequestFor($employer)->employer()->cesuNumber()->value());

        $declaration = SandboxData::standardDeclaration($employer, CesuNumber::fromString(SandboxData::fictitiousEmployeeCesuNumber()));
        static::assertInstanceOf(StandardDeclaration::class, $declaration);
    }

    public function testFailureMessagesNeverEchoUrssafTexts(): void
    {
        $rejected = UrssafRequestRejected::withErrors(
            Operation::VerifyEmployer,
            400,
            ApiErrors::of(ApiError::of('PARAM_INVALIDE', 'echoes Z0000000000000', 'description Z0000000000000')),
            ErrorKind::InvalidParameter,
            null,
        );

        $text = SandboxAssert::describe($rejected);

        static::assertStringContainsString('verify_employer', $text);
        static::assertStringContainsString('PARAM_INVALIDE', $text);
        static::assertStringNotContainsString('Z0000000000000', $text);
        static::assertStringContainsString('URSSAF_SANDBOX_TOKEN_URI', SandboxAssert::describe(UrssafAuthenticationFailed::withStatus(401)));
        static::assertSame('RuntimeException', SandboxAssert::describe(new RuntimeException('Z0000000000000')));
        SandboxAssert::allCodesCatalogued($rejected->errors());
    }

    /**
     * @return array<string, mixed>
     */
    private function readReport(): array
    {
        // @mago-expect analysis:mixed-assignment The decoded report is untyped: it is asserted to be an array right below.
        $decoded = json_decode((string) file_get_contents($this->reportPath), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
