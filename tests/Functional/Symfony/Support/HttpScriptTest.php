<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateAici;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateCesuPlus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\CancelMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\EstimateContributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Standalone\UrssafTierceDeclarationCesu;
use TheBeardedBear\UrssafTierceDeclarationCesu\Standalone\UrssafTierceDeclarationCesuBuilder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Testing\SimulatorSamples;
use Throwable;

/**
 * The scripted platform answers each of the nine operations through the real HTTP gateway (the package built over a
 * mock PSR-18 client), counts the calls per route and can be switched to a status or to a transport failure.
 */
final class HttpScriptTest extends TestCase
{
    /**
     * The route, then the routes called before it (the operation needs a tracked employer with a local mandate, ADR 0009).
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function routes(): iterable
    {
        yield HttpScript::ROUTE_010 => [HttpScript::ROUTE_010, []];
        yield HttpScript::ROUTE_020 => [HttpScript::ROUTE_020, []];
        yield HttpScript::ROUTE_021 => [HttpScript::ROUTE_021, []];
        yield HttpScript::ROUTE_030 => [HttpScript::ROUTE_030, []];
        yield HttpScript::ROUTE_031 => [HttpScript::ROUTE_031, [HttpScript::ROUTE_030]];
        yield HttpScript::ROUTE_040 => [HttpScript::ROUTE_040, []];
        yield HttpScript::ROUTE_050 => [HttpScript::ROUTE_050, [HttpScript::ROUTE_030]];
        yield HttpScript::ROUTE_060 => [HttpScript::ROUTE_060, [HttpScript::ROUTE_030]];
        yield HttpScript::ROUTE_070 => [HttpScript::ROUTE_070, []];
    }

    /**
     * @param list<string> $prelude
     */
    #[DataProvider('routes')]
    public function testEachRouteIsMatchedByItsRequest(string $route, array $prelude): void
    {
        $http = new Client();
        $script = new HttpScript($http);

        self::operate($route, self::package($http), TenantId::fromString('tenant-1'));

        static::assertSame(1, $script->calls($route));
        static::assertSame(1, $script->calls(HttpScript::ROUTE_AUTHENTICATION));
        foreach ($prelude as $called) {
            static::assertSame(1, $script->calls($called));
        }
        foreach (array_diff(array_keys(iterator_to_array(self::routes(), true)), [$route, ...$prelude]) as $other) {
            static::assertSame(0, $script->calls($other), 'Unexpected call of route ' . $other);
        }
    }

    public function testAScriptedStatusIsAnswered(): void
    {
        $http = new Client();
        $script = new HttpScript($http);
        $script->respondWithFixture(HttpScript::ROUTE_021, 400, 'error_param_invalide.json');

        $this->expectException(UrssafRequestRejected::class);

        self::package($http)->verifyEmployee()(new VerifyEmployee(
            TenantId::fromString('tenant-1'),
            new EmployeeReferenceDraft(DeclarationSamples::EMPLOYEE_CESU_NUMBER, null, null),
        ));
    }

    public function testAScriptedBodyCarriesItsHeaders(): void
    {
        $http = new Client();
        $script = new HttpScript($http);
        $script->respond(HttpScript::ROUTE_021, 503, '{"message":"unavailable"}', ['Retry-After' => '120']);

        try {
            self::package($http)->verifyEmployee()(new VerifyEmployee(
                TenantId::fromString('tenant-1'),
                new EmployeeReferenceDraft(DeclarationSamples::EMPLOYEE_CESU_NUMBER, null, null),
            ));
            static::fail('A 503 answer must fail.');
        } catch (UrssafUnavailable $failure) {
            static::assertSame(503, $failure->httpStatus());
            static::assertSame(120, $failure->retryAfterSeconds());
        }
    }

    public function testATransportFailureIsRaisedAsUnavailable(): void
    {
        $http = new Client();
        $script = new HttpScript($http);
        $script->failTransport(HttpScript::ROUTE_021);

        try {
            self::package($http)->verifyEmployee()(new VerifyEmployee(
                TenantId::fromString('tenant-1'),
                new EmployeeReferenceDraft(DeclarationSamples::EMPLOYEE_CESU_NUMBER, null, null),
            ));
            static::fail('A transport failure must fail.');
        } catch (UrssafUnavailable $failure) {
            static::assertNull($failure->httpStatus());
            static::assertSame(1, $script->calls(HttpScript::ROUTE_021));
        }
    }

    public function testTheFixturesAreTheWireFixtures(): void
    {
        static::assertStringContainsString('fictitious-access-token', HttpScript::fixture('token_response.json'));
        static::assertSame(HttpScript::fixture('estimate_response.json'), (string) file_get_contents(__DIR__ . '/../../../Fixtures/wire/estimate_response.json'));
    }

    /**
     * Runs the use case of the route through the real gateway.
     *
     * @throws Throwable
     */
    private static function operate(string $route, UrssafTierceDeclarationCesu $package, TenantId $tenant): void
    {
        $reference = ExternalReference::fromString('employer-1');
        $selector = EmployerSelector::byExternalReference($reference);
        if (\in_array($route, [HttpScript::ROUTE_020, HttpScript::ROUTE_030, HttpScript::ROUTE_031, HttpScript::ROUTE_050, HttpScript::ROUTE_060], true)) {
            $package->importEmployer()(new ImportEmployer($tenant, $reference, EmployerSamples::employer()));
        }
        if (\in_array($route, [HttpScript::ROUTE_031, HttpScript::ROUTE_050, HttpScript::ROUTE_060], true)) {
            $package->registerMandate()(new RegisterMandate($tenant, $selector));
        }
        static::assertIsObject(match ($route) {
            HttpScript::ROUTE_010 => $package->registerEmployer()(new RegisterEmployer($tenant, $reference, EmployerSamples::newEmployerDraft())),
            HttpScript::ROUTE_020 => $package->syncEmployerStatus()(new SyncEmployerStatus($tenant, $selector, null)),
            HttpScript::ROUTE_021 => $package->verifyEmployee()(new VerifyEmployee($tenant, new EmployeeReferenceDraft(DeclarationSamples::EMPLOYEE_CESU_NUMBER, null, null))),
            HttpScript::ROUTE_030 => $package->registerMandate()(new RegisterMandate($tenant, $selector)),
            HttpScript::ROUTE_031 => $package->cancelMandate()(new CancelMandate($tenant, $selector)),
            HttpScript::ROUTE_040 => $package->estimateContributions()(new EstimateContributions($tenant, GatewaySamples::estimate())),
            HttpScript::ROUTE_050 => $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('declaration-1'), DraftFixtures::valid())),
            HttpScript::ROUTE_060 => $package->activateCesuPlus()(new ActivateCesuPlus($tenant, EmployerSamples::employer(), DeclarationSamples::employeeCesuNumber(), true)),
            default => $package->activateAici()(new ActivateAici($tenant, SimulatorSamples::aiciRequestFor(EmployerSamples::employer()))),
        });
    }

    private static function package(Client $http): UrssafTierceDeclarationCesu
    {
        $factory = new Psr17Factory();

        return UrssafTierceDeclarationCesuBuilder::create(
            new HttpTransport($http, $factory, $factory),
            new InMemoryCredentialsProvider(['tenant-1' => Credentials::of('client-1', 'secret-1')]),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        )->build();
    }
}
