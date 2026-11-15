<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ContributionEstimator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerRegistrar;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\MandateNotifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\SalaryDeclarer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CancelMandateCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\EstimateContributionsCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\ImportEmployerCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\RegisterMandateCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\RelayOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\RequeueInvalidCredentialsCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\ResolveDeclarationOutcomeCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\SyncEmployerStatusesCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\VerifyEmployeeCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\VerifyEmployerCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\AuthenticationCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\ConfigurationCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\InstallationCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\LockCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\PersistenceCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler\SyncEmployerStatusesMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler\SyncEmployerStatusMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\Psr14IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpHeader;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\App\TestKernel;

/**
 * The services as declared by the bundle, before the container removes, inlines and resolves anything.
 *
 * One test per wired option, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class ServiceDefinitionsTest extends TestCase
{
    private const LOGGER = ['monolog.logger' => [['channel' => 'urssaf_cesu']]];

    public function testTheDedicatedHttpClientUsesTheConfiguredTimeout(): void
    {
        $container = (new TestKernel(['http_timeout' => 5.5]))->definitions();

        static::assertSame([['timeout' => 5.5]], $container->getDefinition(ServiceIds::SYMFONY_HTTP_CLIENT)->getArguments());
        $psr18 = $container->getDefinition(ServiceIds::PSR18_CLIENT)->getArguments();
        static::assertCount(1, $psr18);
        static::assertEquals(new Reference(ServiceIds::SYMFONY_HTTP_CLIENT), $psr18[0]);
    }

    public function testTheDedicatedHttpClientTimeoutDefaultsToFifteenSeconds(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertSame([['timeout' => 15.0]], $container->getDefinition(ServiceIds::SYMFONY_HTTP_CLIENT)->getArguments());
    }

    public function testTheHttpServicesLogOnTheUrssafCesuChannel(): void
    {
        $container = (new TestKernel())->definitions();

        foreach ([ServiceIds::TOKEN_PROVIDER, ServiceIds::PROBE_TOKEN_PROVIDER, ServiceIds::JSON_CLIENT, CesuGateway::class, FailureReporter::class] as $id) {
            static::assertSame(self::LOGGER, $container->getDefinition($id)->getTags(), $id);
        }
    }

    public function testTheFailureReporterAndTheGatewayReceiveTheOptionalPackageLogger(): void
    {
        $container = (new TestKernel())->definitions();
        $logger = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

        $reporter = $container->getDefinition(FailureReporter::class);
        static::assertSame(PsrLoggerFailureReporter::class, $reporter->getClass());
        static::assertEquals([$logger], $reporter->getArguments());
        static::assertEquals($logger, $container->getDefinition(CesuGateway::class)->getArgument('$logger'));
    }

    public function testTheGatewayArgumentsAreThoseOfTheGatewayFactory(): void
    {
        $container = (new TestKernel(['agreement_encoding' => 'numeric']))->definitions();

        $gateway = $container->getDefinition(CesuGateway::class);
        static::assertSame(UrssafHttpGateway::class, $gateway->getClass());
        static::assertEquals(new Reference(CredentialsProvider::class), $gateway->getArgument(0));
        static::assertEquals(new Reference(ServiceIds::JSON_CLIENT), $gateway->getArgument(1));
        static::assertEquals(
            (new Definition(AgreementEncoding::class, ['numeric']))->setFactory([AgreementEncoding::class, 'from']),
            $gateway->getArgument(2),
        );
    }

    public function testTheApiConfigurationArgumentsFollowTheConstructor(): void
    {
        $container = (new TestKernel([
            'base_uri' => 'https://proxy.example.test',
            'request_id_header' => 'X-Request-Id',
            'application_id_header' => ['name' => 'X-Application', 'value' => 'my-application'],
            'token_safety_margin' => 30,
            'business_timezone' => 'America/Martinique',
            'agreement_encoding' => 'numeric',
        ]))->definitions();

        $arguments = $container->getDefinition(ServiceIds::API_CONFIGURATION)->getArguments();

        static::assertEquals((new Definition(Environment::class, ['sandbox']))->setFactory([Environment::class, 'from']), $arguments[0]);
        static::assertSame('https://proxy.example.test', $arguments[1]);
        static::assertSame('X-Request-Id', $arguments[2]);
        static::assertEquals(
            (new Definition(HttpHeader::class, ['X-Application', 'my-application']))->setFactory([HttpHeader::class, 'of']),
            $arguments[3],
        );
        static::assertSame(30, $arguments[4]);
        static::assertEquals(new Definition(DateTimeZone::class, ['America/Martinique']), $arguments[6]);
        static::assertEquals((new Definition(AgreementEncoding::class, ['numeric']))->setFactory([AgreementEncoding::class, 'from']), $arguments[7]);
    }

    public function testTheApplicationIdHeaderIsNotSentWithoutName(): void
    {
        $arguments = (new TestKernel())->definitions()->getDefinition(ServiceIds::API_CONFIGURATION)->getArguments();

        static::assertNull($arguments[2]);
        static::assertNull($arguments[3]);
    }

    public function testTheWorkflowPolicyArgumentsFollowTheConstructor(): void
    {
        $container = (new TestKernel([
            'business_timezone' => 'America/Martinique',
            'workflow' => ['stale_submission_after' => 'PT5M', 'max_submission_attempts' => 3, 'require_local_mandate' => false, 'allow_foster_carer_declarations' => true, 'default_page_size' => 20, 'max_page_size' => 50],
        ]))->definitions();

        static::assertEquals(
            [
                new Definition(DateTimeZone::class, ['America/Martinique']),
                new Definition(DateInterval::class, ['PT5M']),
                3,
                false,
                true,
                20,
                50,
            ],
            $container->getDefinition(WorkflowPolicy::class)->getArguments(),
        );
    }

    public function testOnlyTheHandlersAreDeclaredPublic(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertTrue($container->getDefinition(DeclareSalaryHandler::class)->isPublic());
        static::assertFalse($container->getDefinition(WorkflowPolicy::class)->isPublic());
        static::assertFalse($container->getDefinition(ServiceIds::JSON_CLIENT)->isPublic());
        static::assertFalse($container->getDefinition(Clock::class)->isPublic());
        static::assertFalse($container->getDefinition(CesuGateway::class)->isPublic());
        static::assertFalse($container->getDefinition(IdGenerator::class)->isPublic());
        static::assertFalse($container->getDefinition(RateLimiter::class)->isPublic());
    }

    public function testThePortsOfTheGatewayAreAliasesOfTheGateway(): void
    {
        $container = (new TestKernel())->definitions();

        foreach ([EmployerRegistrar::class, EmployerVerifier::class, EmployeeVerifier::class, MandateNotifier::class, ContributionEstimator::class, SalaryDeclarer::class, CesuPlusActivator::class, AiciActivator::class] as $port) {
            static::assertSame(CesuGateway::class, (string) $container->getAlias($port), $port);
            static::assertFalse($container->hasDefinition($port), $port);
        }
    }

    public function testTheServicesOfTheApplicationAreAliasedInsteadOfDuplicated(): void
    {
        $container = (new TestKernel([
            'credentials' => null,
            'credentials_provider' => 'test.credentials',
            'token_store' => ['service' => 'test.token_store'],
            'persistence' => [
                'driver' => 'custom',
                'employer_account_repository' => 'my.accounts',
                'mandate_repository' => 'my.mandates',
                'declaration_record_repository' => 'my.declarations',
                'transaction_manager' => 'my.transactions',
                'workflow_statistics' => 'my.statistics',
            ],
        ]))->definitions();

        foreach ([
            CredentialsProvider::class => 'test.credentials',
            TokenStore::class => 'test.token_store',
            EmployerAccountRepository::class => 'my.accounts',
            MandateRepository::class => 'my.mandates',
            DeclarationRecordRepository::class => 'my.declarations',
            TransactionManager::class => 'my.transactions',
            WorkflowStatistics::class => 'my.statistics',
        ] as $id => $target) {
            static::assertSame($target, (string) $container->getAlias($id), $id);
            static::assertFalse($container->hasDefinition($id), $id);
        }
    }

    public function testTheInMemoryCredentialsAreBuiltFromTheConfiguration(): void
    {
        $container = (new TestKernel())->definitions();

        $definition = $container->getDefinition(CredentialsProvider::class);
        static::assertSame(InMemoryCredentialsProvider::class, $definition->getClass());
        static::assertFalse($container->hasAlias(CredentialsProvider::class));
        // @mago-expect analysis:mixed-assignment A definition argument is untyped; asserted right below.
        $tenants = $definition->getArgument(0);
        static::assertIsArray($tenants);
        static::assertSame(['tenant-1'], array_keys($tenants));
        // @mago-expect analysis:mixed-assignment A definition argument is untyped; asserted right below.
        $credentials = $tenants['tenant-1'];
        static::assertInstanceOf(Definition::class, $credentials);
        $arguments = $credentials->getArguments();
        static::assertSame('client-1', $arguments[0]);
        static::assertIsString($arguments[1]);
        static::assertStringContainsString('URSSAF_TEST_SECRET', $arguments[1]);
        static::assertSame([Credentials::class, 'of'], $credentials->getFactory());
    }

    public function testTheTokenStoreUsesTheConfiguredCachePool(): void
    {
        $container = (new TestKernel(['token_store' => ['cache_pool' => 'my.pool']]))->definitions();

        static::assertEquals([new Reference('my.pool')], $container->getDefinition(TokenStore::class)->getArguments());
        static::assertFalse($container->hasAlias(TokenStore::class));
    }

    public function testTheProbeTokenProviderHasItsOwnInMemoryTokenStore(): void
    {
        $container = (new TestKernel())->definitions();

        // @mago-expect analysis:mixed-assignment A definition argument is untyped; asserted right below.
        $probe = $container->getDefinition(ServiceIds::PROBE_TOKEN_PROVIDER)->getArgument(3);
        static::assertInstanceOf(Definition::class, $probe);
        static::assertSame(InMemoryTokenStore::class, $probe->getClass());
        static::assertEquals(new Reference(TokenStore::class), $container->getDefinition(ServiceIds::TOKEN_PROVIDER)->getArgument(3));
    }

    public function testTheHttpClientOfTheApplicationReplacesTheDedicatedOneForTheCalls(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertEquals(new Reference('test.http_client'), $container->getDefinition(ServiceIds::JSON_CLIENT)->getArgument(0));
        static::assertEquals(new Reference(ServiceIds::PSR18_CLIENT), $container->getDefinition(ServiceIds::JSON_CLIENT)->getArgument(1));
    }

    public function testTheDedicatedHttpClientIsUsedByDefault(): void
    {
        $container = (new TestKernel(['http_client' => null]))->definitions();

        static::assertEquals(new Reference(ServiceIds::PSR18_CLIENT), $container->getDefinition(ServiceIds::JSON_CLIENT)->getArgument(0));
    }

    public function testNothingIsRegisteredForTheOptionalFeaturesByDefault(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertFalse($container->hasDefinition(UrssafScheduleProvider::class));
        static::assertFalse($container->hasDefinition(SyncEmployerStatusMessageHandler::class));
        static::assertFalse($container->hasDefinition(RelayOutboxHandler::class));
    }

    public function testTheSchedulerDropsTheOutboxTasksWithoutTheOutbox(): void
    {
        $container = (new TestKernel(['scheduler' => ['enabled' => true, 'name' => 'my_schedule', 'frequencies' => ['sync_employer_statuses' => 'PT2H', 'reconcile_stale_declarations' => null]]]))->definitions();

        $definition = $container->getDefinition(UrssafScheduleProvider::class);
        static::assertSame(
            ['sync_employer_statuses' => 'PT2H', 'reconcile_stale_declarations' => null, 'relay_outbox' => null, 'purge_outbox' => null],
            $definition->getArgument(0),
        );
        static::assertSame([['name' => 'my_schedule']], $definition->getTag('scheduler.schedule_provider'));
    }

    public function testTheSchedulerKeepsTheOutboxTasksWithTheOutbox(): void
    {
        $container = (new TestKernel(['scheduler' => ['enabled' => true], 'outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]))->definitions();

        static::assertSame(
            ['sync_employer_statuses' => 'PT1H', 'reconcile_stale_declarations' => 'PT15M', 'relay_outbox' => 'PT1M', 'purge_outbox' => 'P1D'],
            $container->getDefinition(UrssafScheduleProvider::class)->getArgument(0),
        );
        static::assertSame([['name' => 'urssaf_cesu']], $container->getDefinition(UrssafScheduleProvider::class)->getTag('scheduler.schedule_provider'));
    }

    public function testTheMessengerHandlersAreRegisteredWhenEnabled(): void
    {
        $container = (new TestKernel(['messenger' => ['enabled' => true, 'bus' => 'messenger.bus.default']], true))->definitions();

        foreach ([SyncEmployerStatusesMessageHandler::class, SyncEmployerStatusMessageHandler::class] as $handler) {
            static::assertSame([[]], $container->getDefinition($handler)->getTag('messenger.message_handler'), $handler);
            static::assertFalse($container->getDefinition($handler)->isPublic(), $handler);
        }
        static::assertEquals(new Reference('messenger.bus.default'), $container->getDefinition(SyncEmployerStatusesCommand::class)->getArgument('$bus'));
    }

    public function testTheSyncCommandHasNoBusWithoutMessenger(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertNull($container->getDefinition(SyncEmployerStatusesCommand::class)->getArgument('$bus'));
    }

    public function testTheTenantCommandsReceiveTheConfiguredTenants(): void
    {
        $container = (new TestKernel())->definitions();

        foreach ([VerifyEmployerCommand::class, VerifyEmployeeCommand::class, EstimateContributionsCommand::class, ImportEmployerCommand::class, RegisterMandateCommand::class, CancelMandateCommand::class, ResolveDeclarationOutcomeCommand::class, RequeueInvalidCredentialsCommand::class] as $command) {
            $definition = $container->getDefinition($command);
            static::assertSame('%urssaf_tierce_declaration_cesu.tenants%', $definition->getArgument('$configuredTenants'), $command);
            static::assertSame([[]], $definition->getTag('console.command'), $command);
        }
    }

    public function testTheChecksAreRegisteredInReportOrder(): void
    {
        $container = (new TestKernel())->definitions();

        $order = array_keys($container->findTaggedServiceIds(InstallationCheck::TAG));
        static::assertSame(
            [ConfigurationCheck::class, CredentialsCheck::class, AuthenticationCheck::class, TokenStoreCheck::class, PersistenceCheck::class, LockCheck::class],
            $order,
        );
        $authentication = $container->getDefinition(AuthenticationCheck::class)->getArguments();
        static::assertEquals(new Reference(CredentialsProvider::class), $authentication[0]);
        static::assertEquals(new ServiceClosureArgument(new Reference(ServiceIds::PROBE_TOKEN_PROVIDER)), $authentication[1]);
        static::assertEquals([new ServiceClosureArgument(new Reference(ServiceIds::API_CONFIGURATION))], $container->getDefinition(ConfigurationCheck::class)->getArguments());
        static::assertSame([null, 'cache.app'], $container->getDefinition(TokenStoreCheck::class)->getArguments());
        static::assertSame(['in_memory'], $container->getDefinition(PersistenceCheck::class)->getArguments());
        static::assertEquals([new Reference(OperationLocks::class)], $container->getDefinition(LockCheck::class)->getArguments());
        $check = $container->getDefinition(CheckInstallationCommand::class)->getArguments();
        static::assertEquals(new TaggedIteratorArgument(InstallationCheck::TAG), $check[0]);
        static::assertSame('%urssaf_tierce_declaration_cesu.tenants%', $check[1]);
    }

    public function testTheSimulatedGatewayIsCheckedInsteadOfTheAuthentication(): void
    {
        $container = (new TestKernel(['gateway' => ['simulated' => true], 'token_store' => ['service' => 'test.token_store']]))->definitions();

        $order = array_keys($container->findTaggedServiceIds(InstallationCheck::TAG));
        static::assertSame(
            [ConfigurationCheck::class, CredentialsCheck::class, SimulatedGatewayCheck::class, TokenStoreCheck::class, PersistenceCheck::class, LockCheck::class],
            $order,
        );
        static::assertSame([], $container->getDefinition(SimulatedGatewayCheck::class)->getArguments());
        static::assertSame(['test.token_store', 'cache.app'], $container->getDefinition(TokenStoreCheck::class)->getArguments());
    }

    public function testTheViolationListConverterIsPublicAndUsesTheTranslatorWhenThereIsOne(): void
    {
        $definition = (new TestKernel())->definitions()->getDefinition(ViolationListConverter::class);

        static::assertTrue($definition->isPublic());
        static::assertEquals([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $definition->getArguments());
    }

    public function testTheOutboxSinks(): void
    {
        $messenger = (new TestKernel(['outbox' => ['enabled' => true, 'bus' => 'messenger.bus.default']], true))->definitions();
        $dispatcher = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]))->definitions();
        $custom = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'test.sink', 'service' => 'test.outbox']]))->definitions();

        static::assertSame(MessengerIntegrationEventSink::class, $messenger->getDefinition(IntegrationEventSink::class)->getClass());
        static::assertEquals([new Reference('messenger.bus.default')], $messenger->getDefinition(IntegrationEventSink::class)->getArguments());
        static::assertSame(InMemoryOutbox::class, $messenger->getDefinition(Outbox::class)->getClass());
        static::assertSame(Psr14IntegrationEventSink::class, $dispatcher->getDefinition(IntegrationEventSink::class)->getClass());
        static::assertEquals([new Reference('event_dispatcher')], $dispatcher->getDefinition(IntegrationEventSink::class)->getArguments());
        static::assertSame('test.sink', (string) $custom->getAlias(IntegrationEventSink::class));
        static::assertSame('test.outbox', (string) $custom->getAlias(Outbox::class));
        static::assertFalse($custom->hasDefinition(Outbox::class));
    }

    public function testTheOutboxCommandsAndHandlersAreRegistered(): void
    {
        $container = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]))->definitions();

        static::assertTrue($container->getDefinition(RelayOutboxHandler::class)->isPublic());
        static::assertTrue($container->getDefinition(PurgeOutboxHandler::class)->isPublic());
        static::assertSame([[]], $container->getDefinition(RelayOutboxCommand::class)->getTag('console.command'));
        static::assertSame([[]], $container->getDefinition(PurgeOutboxCommand::class)->getTag('console.command'));
        static::assertEquals(new Reference(PurgeOutboxHandler::class), $container->getDefinition(PurgeOutboxCommand::class)->getArgument(0));
    }

    public function testTheOperationLocksReceiveTheFactoryOfTheConfiguration(): void
    {
        $required = (new TestKernel(['lock' => ['enabled' => true, 'factory' => 'my.lock_factory']], false, true))->definitions();
        $auto = (new TestKernel(['lock' => ['factory' => 'my.lock_factory']], false, true))->definitions();
        $disabled = (new TestKernel(['lock' => ['enabled' => false]], false, true))->definitions();

        static::assertEquals(new Reference('my.lock_factory'), $required->getDefinition(OperationLocks::class)->getArgument(0));
        static::assertEquals(new Reference('my.lock_factory', ContainerInterface::NULL_ON_INVALID_REFERENCE), $auto->getDefinition(OperationLocks::class)->getArgument(0));
        static::assertNull($disabled->getDefinition(OperationLocks::class)->getArgument(0));
    }
}
