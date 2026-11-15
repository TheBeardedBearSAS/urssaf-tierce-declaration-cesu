<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * "gateway.simulated: true": the whole workflow runs against the in-memory URSSAF platform, as in the tests of a host.
 */
final class SimulatedGatewayTest extends TestCase
{
    public function testTheRealGatewayIsUsedByDefault(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(UrssafHttpGateway::class, $harness->privateService(CesuGateway::class, CesuGateway::class));
        static::assertFalse($harness->has(SimulatedCesuGateway::class));
    }

    public function testTheWorkflowRunsAgainstTheSimulatorWithoutNetwork(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $urssaf = $harness->get(SimulatedCesuGateway::class);
        static::assertSame($urssaf, $harness->privateService(CesuGateway::class, CesuGateway::class));
        $employer = EmployerSamples::employer();
        $urssaf->knowEmployer($employer);
        $tenant = TenantId::fromString('tenant-1');
        $reference = ExternalReference::fromString('employer-1');

        $harness->get(ImportEmployerHandler::class)(new ImportEmployer($tenant, $reference, $employer));
        static::assertSame(0, $harness->command('urssaf:cesu:mandate:register')->execute(['--employer' => 'employer-1']));
        $submission = $harness->get(DeclareSalaryHandler::class)(new DeclareSalary($tenant, ExternalReference::fromString('declaration-1'), DraftFixtures::valid()));
        static::assertSame(0, $harness->command('urssaf:cesu:employers:sync-status')->execute(['--tenant' => 'tenant-1']));

        static::assertSame('declared', $submission->outcome->value);
        static::assertSame([Operation::RegisterMandate, Operation::DeclareSalary, Operation::VerifyEmployer], $urssaf->calls());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testTheSimulatorIsRefusedWhenAPlaceholderResolvesToProduction(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'production';
        $_SERVER['URSSAF_TEST_ENVIRONMENT'] = 'production';
        try {
            $harness = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%', 'gateway' => ['simulated' => true]]);

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('"gateway.simulated" is meant for tests and cannot be used with the production environment.');

            $harness->get(SimulatedCesuGateway::class);
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT'], $_SERVER['URSSAF_TEST_ENVIRONMENT']);
        }
    }

    public function testTheSimulatorIsAcceptedWhenAPlaceholderResolvesToTheSandbox(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'sandbox';
        $_SERVER['URSSAF_TEST_ENVIRONMENT'] = 'sandbox';
        try {
            $harness = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%', 'gateway' => ['simulated' => true]]);

            static::assertInstanceOf(SimulatedCesuGateway::class, $harness->get(SimulatedCesuGateway::class));
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT'], $_SERVER['URSSAF_TEST_ENVIRONMENT']);
        }
    }
}
