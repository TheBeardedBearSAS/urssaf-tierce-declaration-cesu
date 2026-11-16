<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Standalone;

use DateInterval;
use DateTimeZone;
use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateAici;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateCesuPlus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\CancelMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ForgetEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ReconcileStaleDeclarations;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RequeueAfterInvalidCredentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ResolveDeclarationOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncAllEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\EmployerErasureRefused;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\EstimateContributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\FindDeclarations;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetEmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\EmploymentPeriodDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\InMemoryEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Standalone\UrssafTierceDeclarationCesu;
use TheBeardedBear\UrssafTierceDeclarationCesu\Standalone\UrssafTierceDeclarationCesuBuilder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\ErasurePolicies;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\FailingEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\FixedClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\SequentialIdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Testing\SimulatorSamples;

/**
 * The standalone builder and facade, end to end: the simulator path and the HTTP gateway over a scripted PSR-18
 * client (reference UrssafTiersPrestationsBuilderTest; docs/standalone.md).
 *
 * One test per builder option or scenario, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class UrssafTierceDeclarationCesuBuilderTest extends TestCase
{
    private const NOW = '2026-11-15T10:00:00Z';

    public function testEveryUseCaseIsAvailable(): void
    {
        $expected = [];
        foreach (['Command', 'Query'] as $namespace) {
            $files = glob(__DIR__ . '/../../../src/Application/' . $namespace . '/*Handler.php');
            static::assertIsArray($files);
            foreach ($files as $file) {
                $expected[] = 'TheBeardedBear\UrssafTierceDeclarationCesu\Application\\' . $namespace . '\\' . basename($file, '.php');
            }
        }
        $available = [];
        foreach ((new ReflectionClass(UrssafTierceDeclarationCesu::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if ($method->isConstructor() || !$type instanceof ReflectionNamedType || !str_ends_with($type->getName(), 'Handler')) {
                continue;
            }
            $available[] = $type->getName();
        }
        sort($available);
        sort($expected);

        static::assertSame($expected, $available);
        static::assertCount(23, $available);
    }

    public function testTheWholeWorkflowRunsWithTheSimulator(): void
    {
        $urssaf = new SimulatedCesuGateway();
        $urssaf->knowEmployer(EmployerSamples::employer());
        $urssaf->knowEmployee(DeclarationSamples::employeeCesuNumber());
        $clock = new FixedClock(self::NOW);
        $events = new InMemoryEventPublisher();
        $sink = new RecordingIntegrationEventSink();
        $package = self::builder()
            ->withGateway($urssaf)
            ->withClock($clock)
            ->withIdGenerator(new SequentialIdGenerator())
            ->withEventPublisher($events)
            ->withOutbox(new InMemoryOutbox(), $sink)
            ->build();
        $tenant = TenantId::fromString('tenant-1');
        $reference = ExternalReference::fromString('employer-1');
        $employer = EmployerSelector::byExternalReference($reference);

        $imported = $package->importEmployer()(new ImportEmployer($tenant, $reference, EmployerSamples::employer()));
        $mandate = $package->registerMandate()(new RegisterMandate($tenant, $employer));
        $synced = $package->syncEmployerStatus()(new SyncEmployerStatus($tenant, $employer, null));
        $employee = $package->verifyEmployee()(new VerifyEmployee($tenant, new EmployeeReferenceDraft(DeclarationSamples::EMPLOYEE_CESU_NUMBER, null, null)));
        $estimate = $package->estimateContributions()(new EstimateContributions($tenant, GatewaySamples::estimate()));
        $declared = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-1'), DraftFixtures::valid()));
        $replayed = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-1'), DraftFixtures::valid()));
        $duplicate = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-2'), DraftFixtures::valid()));

        static::assertSame('registered', $imported->stage);
        static::assertSame('notified', $mandate->state);
        static::assertSame('mandate_confirmed', $synced->stage);
        static::assertTrue($employee->registered);
        static::assertSame(6310, $estimate->employerContributionsCents);
        static::assertSame(2009, $estimate->employeeContributionsCents);
        static::assertSame(ItemOutcome::Declared, $declared->outcome);
        static::assertSame('SIM-DECL-000001', $declared->declaration->status->declarationId);
        static::assertSame(ItemOutcome::Replayed, $replayed->outcome);
        static::assertSame(ItemOutcome::AlreadyDeclared, $duplicate->outcome);
        static::assertSame(
            [Operation::RegisterMandate, Operation::VerifyEmployer, Operation::VerifyEmployee, Operation::EstimateContributions, Operation::DeclareSalary, Operation::DeclareSalary],
            $urssaf->calls(),
        );

        $package->activateCesuPlus()(new ActivateCesuPlus($tenant, EmployerSamples::employer(), DeclarationSamples::employeeCesuNumber(), true));
        $package->activateAici()(new ActivateAici($tenant, SimulatorSamples::aiciRequestFor(EmployerSamples::employer())));
        $aici = $package->syncEmployerStatus()(new SyncEmployerStatus($tenant, $employer, null));
        $account = $package->getEmployerAccount()(new GetEmployerAccount($tenant, $employer));
        $record = $package->getDeclaration()(new GetDeclaration($tenant, DeclarationSelector::byExternalReference(ExternalReference::fromString('payslip-1'))));
        $inState = $package->findDeclarations()(FindDeclarations::inState($tenant, DeclarationState::Declared, null));
        $registered = $package->registerEmployer()(new RegisterEmployer($tenant, ExternalReference::fromString('employer-2'), EmployerSamples::newEmployerDraft()));
        $cancelled = $package->cancelMandate()(new CancelMandate($tenant, $employer));
        $overview = $package->getWorkflowOverview()(GetWorkflowOverview::forTenant($tenant));

        static::assertSame('aici_active', $aici->stage);
        static::assertSame('aici_active', $account->stage);
        static::assertSame('2026-11-15T10:00:00Z', $account->createdAt);
        static::assertSame($declared->declaration->recordId, $record->recordId);
        static::assertSame('id-3', $record->recordId);
        static::assertSame([$record->recordId], array_map(static fn(DeclarationView $view): string => $view->recordId, $inState));
        static::assertSame('SIM00000000001', $registered->cesuNumber);
        static::assertSame('cancelled', $cancelled->state);
        static::assertSame(1, $overview[0]->declarations['declared']);
        static::assertSame(1, $overview[0]->declarations['already_declared']);
        static::assertSame(1, $overview[0]->mandates['cancelled']);
        static::assertSame([$urssaf, $urssaf], [$package->gateway(), $package->gateway()]);
        static::assertSame(
            [
                Operation::RegisterMandate,
                Operation::VerifyEmployer,
                Operation::VerifyEmployee,
                Operation::EstimateContributions,
                Operation::DeclareSalary,
                Operation::DeclareSalary,
                Operation::ActivateCesuPlus,
                Operation::ActivateAici,
                Operation::VerifyEmployer,
                Operation::RegisterEmployer,
                Operation::CancelMandate,
            ],
            $urssaf->calls(),
        );
        static::assertNotSame([], $events->published());

        $relay = $package->relayOutbox()(new RelayOutbox(null));
        static::assertSame(11, $relay->dispatched);
        static::assertSame(0, $relay->failed);
        static::assertCount($relay->dispatched, $sink->deliveredIds());
        $clock->moveTo('2026-12-31T00:00:00Z');
        static::assertSame($relay->dispatched, $package->purgeOutbox()(new PurgeOutbox(new DateInterval('P30D'))));
    }

    public function testTheDoctrinePersistenceIsUsedWithItsStatistics(): void
    {
        $entityManager = TestDatabase::entityManager();
        $urssaf = new SimulatedCesuGateway();
        $package = self::builder()->withGateway($urssaf)->withDoctrine($entityManager)->build();
        $tenant = TenantId::fromString('tenant-1');
        $reference = ExternalReference::fromString('employer-1');

        $imported = $package->importEmployer()(new ImportEmployer($tenant, $reference, EmployerSamples::employer()));
        $entityManager->clear();

        $stored = (new DoctrineEmployerAccountRepository($entityManager))->findByExternalReference($tenant, $reference);
        static::assertNotNull($stored);
        static::assertSame($imported->accountId, $stored->id()->value());
        static::assertSame(1, $package->getWorkflowOverview()(GetWorkflowOverview::forTenant($tenant))[0]->employers['registered']);
    }

    public function testAnUnknownOutcomeIsResolvedByAnOperator(): void
    {
        $urssaf = new SimulatedCesuGateway();
        $urssaf->knowEmployer(EmployerSamples::employer());
        $urssaf->mandate(DeclarationSamples::employerCesuNumber(), TenantId::fromString('tenant-1'));
        $package = self::builder()->withGateway($urssaf)->withClock(new FixedClock(self::NOW))->withIdGenerator(new SequentialIdGenerator())->build();
        $tenant = TenantId::fromString('tenant-1');
        $employer = EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1'));
        $package->importEmployer()(new ImportEmployer($tenant, ExternalReference::fromString('employer-1'), EmployerSamples::employer()));
        $package->syncEmployerStatus()(new SyncEmployerStatus($tenant, $employer, null));

        $urssaf->loseNextAnswer(Operation::DeclareSalary);
        $first = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-a'), DraftFixtures::valid()));
        $package->resolveDeclarationOutcome()(ResolveDeclarationOutcome::asNotReceived($tenant, DeclarationSelector::byExternalReference(ExternalReference::fromString('payslip-a'))));
        $again = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-a'), DraftFixtures::valid()));

        static::assertSame(ItemOutcome::OutcomeUnknown, $first->outcome);
        static::assertCount(1, $urssaf->declarationIds());
        static::assertSame(ItemOutcome::AlreadyDeclared, $again->outcome);

        $otherPeriod = DraftFixtures::withParts(new EmploymentPeriodDraft('2026-10-01', '2026-10-30'), null, null, null, null);
        $urssaf->loseNextAnswer(Operation::DeclareSalary);
        $second = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-b'), $otherPeriod));
        $resolved = $package->resolveDeclarationOutcome()(ResolveDeclarationOutcome::asDeclared(
            $tenant,
            DeclarationSelector::byExternalReference(ExternalReference::fromString('payslip-b')),
            $urssaf->declarationIds()[1],
        ));

        static::assertSame(ItemOutcome::OutcomeUnknown, $second->outcome);
        static::assertSame('declared', $resolved->status->state);
        static::assertSame('SIM-DECL-000002', $resolved->status->declarationId);
    }

    public function testInvalidCredentialsAreRequeued(): void
    {
        $urssaf = SimulatorSamples::mandatedGateway();
        $package = self::builder()->withGateway($urssaf)->withClock(new FixedClock(self::NOW))->withIdGenerator(new SequentialIdGenerator())->build();
        $tenant = TenantId::fromString('tenant-1');
        $command = new DeclareSalary($tenant, ExternalReference::fromString('payslip-1'), DraftFixtures::valid(), true);

        $urssaf->failNext(Operation::DeclareSalary, UrssafAuthenticationFailed::withStatus(401));
        $first = $package->declareSalary()($command);
        $requeued = $package->requeueAfterInvalidCredentials()(new RequeueAfterInvalidCredentials($tenant, null));
        $second = $package->declareSalary()($command);

        static::assertSame(ItemOutcome::InvalidCredentials, $first->outcome);
        static::assertSame(1, $requeued->declarations);
        static::assertSame(ItemOutcome::Declared, $second->outcome);
    }

    public function testTheBatchUseCasesRunOnAnEmptyStore(): void
    {
        $urssaf = new SimulatedCesuGateway();
        $package = self::builder()->withGateway($urssaf)->withClock(new FixedClock(self::NOW))->build();
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame([], $package->syncAllEmployerStatuses()(new SyncAllEmployerStatuses(null))->items());
        static::assertSame([], $package->syncEmployerStatuses()(new SyncEmployerStatuses($tenant, null))->items());
        static::assertSame([], $package->reconcileStaleDeclarations()(new ReconcileStaleDeclarations(null))->items());
        static::assertSame(0, $package->requeueAfterInvalidCredentials()(new RequeueAfterInvalidCredentials($tenant, null))->declarations);
        static::assertSame([], $package->findDeclarations()(FindDeclarations::inState($tenant, DeclarationState::Pending, null)));
        static::assertSame([], $urssaf->calls());
    }

    public function testThePolicyIsUsed(): void
    {
        $tenant = TenantId::fromString('tenant-1');
        $command = new DeclareSalary($tenant, ExternalReference::fromString('payslip-1'), DraftFixtures::valid());
        $permissive = new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('PT15M'), 5, false);

        $urssaf = SimulatorSamples::mandatedGateway();
        $package = self::builder()->withGateway($urssaf)->withClock(new FixedClock(self::NOW))->withPolicy($permissive)->build();
        $package->importEmployer()(new ImportEmployer($tenant, ExternalReference::fromString('employer-1'), EmployerSamples::employer()));
        $declared = $package->declareSalary()($command);

        $strictGateway = SimulatorSamples::mandatedGateway();
        $strict = self::builder()->withGateway($strictGateway)->withClock(new FixedClock(self::NOW))->build();
        $strict->importEmployer()(new ImportEmployer($tenant, ExternalReference::fromString('employer-1'), EmployerSamples::employer()));

        static::assertSame(ItemOutcome::Declared, $declared->outcome);
        try {
            $strict->declareSalary()($command);
            static::fail('The mandate guard must refuse the declaration.');
        } catch (MandateRequired) {
            static::assertSame([], $strictGateway->calls());
        }
    }

    public function testAnImportedEmployerIsErasedWithoutAnyCallToUrssaf(): void
    {
        $urssaf = SimulatorSamples::mandatedGateway();
        $package = self::builder()->withGateway($urssaf)->withClock(new FixedClock(self::NOW))->withIdGenerator(new SequentialIdGenerator())->withPolicy(ErasurePolicies::withoutMandateGuard())->build();
        $tenant = TenantId::fromString('tenant-1');
        $reference = ExternalReference::fromString('employer-1');
        $package->importEmployer()(new ImportEmployer($tenant, $reference, EmployerSamples::employer()));
        $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-1'), DraftFixtures::valid()));
        $calls = $urssaf->calls();

        $erasure = $package->forgetEmployer()(new ForgetEmployer($tenant, EmployerSelector::byExternalReference($reference), null, null));

        static::assertSame(['id-1'], [$erasure->accountId]);
        static::assertSame([], $erasure->mandateIds);
        static::assertSame(['id-2'], $erasure->declarationRecordIds);
        static::assertSame($calls, $urssaf->calls());
        static::assertSame([], $package->findDeclarations()(FindDeclarations::inState($tenant, DeclarationState::Declared, null)));
        $this->expectException(RecordNotFound::class);
        $package->getEmployerAccount()(new GetEmployerAccount($tenant, EmployerSelector::byExternalReference($reference)));
    }

    public function testTheErasureOptInsComeFromThePolicy(): void
    {
        $tenant = TenantId::fromString('tenant-1');
        $reference = ExternalReference::fromString('employer-1');
        $command = new ForgetEmployer($tenant, EmployerSelector::byExternalReference($reference), null, null);
        $packages = [];
        foreach ([ErasurePolicies::of(false, false), ErasurePolicies::of(false, true)] as $policy) {
            $urssaf = new SimulatedCesuGateway();
            $urssaf->knowEmployer(EmployerSamples::employer());
            $package = self::builder()->withGateway($urssaf)->withClock(new FixedClock(self::NOW))->withPolicy($policy)->build();
            $package->importEmployer()(new ImportEmployer($tenant, $reference, EmployerSamples::employer()));
            $package->registerMandate()(new RegisterMandate($tenant, EmployerSelector::byExternalReference($reference)));
            $packages[] = $package;
        }
        [$strict, $permissive] = $packages;

        try {
            $strict->forgetEmployer()($command);
            static::fail('The active mandate must refuse the erasure.');
        } catch (EmployerErasureRefused $refusal) {
            static::assertSame('employer_account.forget_active_mandate', $refusal->violationCode());
        }

        static::assertCount(1, $permissive->forgetEmployer()($command)->mandateIds);
    }

    public function testTheHttpGatewayIsBuiltFromTheTransportAndItsOptions(): void
    {
        $http = new Client();
        $http->addResponse(new Response(200, [], Fixtures::load('token_response')));
        $http->addResponse(new Response(200, [], Fixtures::load('verify_employer_response_assembled')));
        $http->addResponse(new Response(200, [], ''));
        $http->addResponse(new Response(200, [], Fixtures::load('declare_response_assembled')));
        $http->addResponse(new Response(400, [], Fixtures::load('error_mandat_inexistant')));
        $logger = new CapturingLogger();
        $package = self::builderOver($http)
            ->withClock(new FixedClock(self::NOW))
            ->withIdGenerator(new SequentialIdGenerator())
            ->withLogger($logger)
            ->build();
        $tenant = TenantId::fromString('tenant-1');

        $status = $package->verifyEmployer()(new VerifyEmployer($tenant, EmployerSamples::employer(), null));
        $package->importEmployer()(new ImportEmployer($tenant, ExternalReference::fromString('employer-1'), EmployerSamples::employer()));
        $package->registerMandate()(new RegisterMandate($tenant, EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1'))));
        $declared = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-1'), DraftFixtures::valid()));
        $rejected = $package->declareSalary()(new DeclareSalary($tenant, ExternalReference::fromString('payslip-2'), DraftFixtures::withParts(new EmploymentPeriodDraft('2026-10-01', '2026-10-30'), null, null, null, null)));

        static::assertInstanceOf(UrssafHttpGateway::class, $package->gateway());
        static::assertTrue($status->registered);
        $verification = $http->getRequests()[1];
        static::assertSame('GET', $verification->getMethod());
        static::assertSame('/tiersdecl/v1/cesu/employeurs/Z0003752390009', $verification->getUri()->getPath());
        static::assertSame('dateNaissance=1975-01-30', $verification->getUri()->getQuery());
        static::assertSame(ItemOutcome::Declared, $declared->outcome);
        static::assertSame('209299542888', $declared->declaration->status->declarationId);
        static::assertSame(ItemOutcome::Rejected, $rejected->outcome);
        static::assertSame('MANDAT_INEXISTANT', $rejected->declaration->status->errors[0]['code']);
        $messages = array_column($logger->records(), 'message');
        static::assertContains('URSSAF token request.', $messages);
        static::assertContains('URSSAF call.', $messages);
    }

    public function testTheRateLimiterAndTheTokenStoreAreUsed(): void
    {
        $limited = new Client();
        $package = self::builderOver($limited)->withRateLimiter(new RejectingRateLimiter())->build();
        $tenant = TenantId::fromString('tenant-1');

        try {
            $package->verifyEmployer()(new VerifyEmployer($tenant, EmployerSamples::employer(), null));
            static::fail('The rate limiter must refuse the call.');
        } catch (RateLimitExceeded) {
            static::assertSame([], $limited->getRequests());
        }

        $tokens = new InMemoryTokenStore();
        $first = new Client();
        $first->addResponse(new Response(200, [], Fixtures::load('token_response')));
        $first->addResponse(new Response(200, [], Fixtures::load('verify_employer_response_assembled')));
        $second = new Client();
        $second->addResponse(new Response(200, [], Fixtures::load('verify_employer_response_assembled')));
        $query = new VerifyEmployer($tenant, EmployerSamples::employer(), null);

        self::builderOver($first)->withClock(new FixedClock(self::NOW))->withTokenStore($tokens)->build()->verifyEmployer()($query);
        self::builderOver($second)->withClock(new FixedClock(self::NOW))->withTokenStore($tokens)->build()->verifyEmployer()($query);

        static::assertCount(2, $first->getRequests());
        static::assertCount(1, $second->getRequests());
    }

    public function testTheLoggerReceivesTheEventPublicationFailures(): void
    {
        $logger = new CapturingLogger();
        $package = self::builder()
            ->withGateway(new SimulatedCesuGateway())
            ->withLogger($logger)
            ->withEventPublisher(new FailingEventPublisher(new RuntimeException('down')))
            ->build();

        $package->importEmployer()(new ImportEmployer(TenantId::fromString('tenant-1'), ExternalReference::fromString('employer-1'), EmployerSamples::employer()));

        static::assertNotSame([], $logger->records());
    }

    public function testTheEventPublisherReceivesTheDomainEvents(): void
    {
        $events = new InMemoryEventPublisher();
        $package = self::builder()->withGateway(new SimulatedCesuGateway())->withEventPublisher($events)->build();

        $package->importEmployer()(new ImportEmployer(TenantId::fromString('tenant-1'), ExternalReference::fromString('employer-1'), EmployerSamples::employer()));

        static::assertCount(1, $events->published());
    }

    public function testCustomRepositoriesAreUsed(): void
    {
        $accounts = new InMemoryEmployerAccountRepository();
        $package = self::builder()
            ->withGateway(new SimulatedCesuGateway())
            ->withRepositories($accounts, new InMemoryMandateRepository(), new InMemoryDeclarationRecordRepository(), new ImmediateTransactionManager(), null)
            ->build();

        $package->importEmployer()(new ImportEmployer(TenantId::fromString('tenant-1'), ExternalReference::fromString('employer-1'), EmployerSamples::employer()));

        static::assertSame(['tenant-1'], array_map(static fn(TenantId $tenant): string => $tenant->value(), $accounts->tenants()));
    }

    public function testTheOverviewNeedsStatistics(): void
    {
        $package = self::builder()
            ->withRepositories(new InMemoryEmployerAccountRepository(), new InMemoryMandateRepository(), new InMemoryDeclarationRecordRepository(), new ImmediateTransactionManager(), null)
            ->build();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No WorkflowStatistics: give one with the repositories (withRepositories()).');

        $package->getWorkflowOverview();
    }

    public function testTheRelayNeedsASink(): void
    {
        $package = self::builder()->build();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No IntegrationEventSink: give the outbox and its sink (withOutbox()).');

        $package->relayOutbox();
    }

    public function testABuilderIsNeverModified(): void
    {
        $builder = self::builderOver(new Client());
        $derived = $builder->withGateway(new SimulatedCesuGateway());

        static::assertInstanceOf(UrssafHttpGateway::class, $builder->build()->gateway());
        static::assertInstanceOf(SimulatedCesuGateway::class, $derived->build()->gateway());
    }

    private static function builder(): UrssafTierceDeclarationCesuBuilder
    {
        return self::builderOver(new Client());
    }

    private static function builderOver(Client $http): UrssafTierceDeclarationCesuBuilder
    {
        $factory = new Psr17Factory();

        return UrssafTierceDeclarationCesuBuilder::create(
            new HttpTransport($http, $factory, $factory),
            new InMemoryCredentialsProvider(['tenant-1' => Credentials::of('client-1', 'secret-1')]),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
        );
    }
}
