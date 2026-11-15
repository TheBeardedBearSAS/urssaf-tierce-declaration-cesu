<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\HttpClient\Psr18Client;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\UrssafTierceDeclarationCesuBundle;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;

/**
 * Processed defaults of the configuration tree and the tenants parameter (lot 6 design §2, §3 and §4.7).
 */
final class BundleConfigurationTest extends TestCase
{
    public function testTheMinimalConfigurationUsesTheDocumentedDefaults(): void
    {
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        $processed = $this->process(['environment' => 'sandbox', 'credentials' => ['tenant-1' => ['client_id' => 'client-1', 'client_secret' => 'secret-1']]]);

        // The keys given by the application come first, then the tree order.
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        static::assertSame(
            [
                'environment' => 'sandbox',
                'credentials' => ['tenant-1' => ['client_id' => 'client-1', 'client_secret' => 'secret-1']],
                'base_uri' => null,
                'request_id_header' => null,
                'application_id_header' => ['name' => null, 'value' => null],
                'agreement_encoding' => 'boolean',
                'token_safety_margin' => 60,
                'business_timezone' => 'Europe/Paris',
                'http_client' => null,
                'http_timeout' => 15.0,
                'credentials_provider' => null,
                'tenants' => [],
                'token_store' => ['cache_pool' => 'cache.app', 'service' => null],
                'gateway' => ['simulated' => false],
                'rate_limiter' => ['enabled' => false, 'limit' => 2500, 'window' => 3600],
                'lock' => ['enabled' => 'auto', 'factory' => 'lock.factory', 'ttl' => 3600.0],
                'outbox' => ['enabled' => false, 'service' => null, 'sink' => 'messenger', 'bus' => 'messenger.default_bus', 'retention' => 'P30D'],
                'scheduler' => [
                    'enabled' => false,
                    'name' => 'urssaf_cesu',
                    'frequencies' => [
                        'sync_employer_statuses' => 'PT1H',
                        'reconcile_stale_declarations' => 'PT15M',
                        'relay_outbox' => 'PT1M',
                        'purge_outbox' => 'P1D',
                    ],
                ],
                'workflow' => [
                    'stale_submission_after' => 'PT15M',
                    'max_submission_attempts' => 5,
                    'require_local_mandate' => true,
                    'allow_foster_carer_declarations' => false,
                    'default_page_size' => 100,
                    'max_page_size' => 1000,
                ],
                'persistence' => [
                    'driver' => 'in_memory',
                    'employer_account_repository' => null,
                    'mandate_repository' => null,
                    'declaration_record_repository' => null,
                    'transaction_manager' => null,
                    'workflow_statistics' => null,
                ],
                'messenger' => ['enabled' => false, 'bus' => 'messenger.default_bus'],
            ],
            $processed,
        );
    }

    public function testTheMinimalConfigurationCompiles(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(DeclareSalaryHandler::class, $harness->get(DeclareSalaryHandler::class));
        static::assertInstanceOf(NoopRateLimiter::class, $harness->privateService(RateLimiter::class, RateLimiter::class));
        static::assertInstanceOf(Psr18Client::class, $harness->privateService(ServiceIds::PSR18_CLIENT, Psr18Client::class));
        static::assertSame(['tenant-1'], $harness->kernel->getContainer()->getParameter(UrssafTierceDeclarationCesuBundle::PARAMETER_TENANTS));
    }

    public function testTheMinimalConfigurationWiresTheDocumentedDefaults(): void
    {
        $harness = Harness::boot();
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);
        $api = $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);

        static::assertSame('Europe/Paris', $policy->businessTimeZone()->getName());
        static::assertSame(15, $policy->staleSubmissionAfter()->i);
        static::assertSame(5, $policy->maxSubmissionAttempts());
        static::assertTrue($policy->requireLocalMandate());
        static::assertFalse($policy->allowFosterCarerDeclarations());
        static::assertSame(100, $policy->pageSize(null));
        static::assertSame(1000, $policy->pageSize(5000));
        static::assertSame('Europe/Paris', $api->businessTimeZone()->getName());
        static::assertSame(Environment::Sandbox, $api->environment());
        static::assertSame(60, $api->tokenSafetyMarginSeconds());
        static::assertNull($api->requestIdHeaderName());
        static::assertNull($api->applicationIdHeader());
        static::assertSame('boolean', $api->agreementEncoding()->value);
    }

    public function testOnlyTheHandlersArePublic(): void
    {
        $harness = Harness::boot();

        static::assertTrue($harness->has(DeclareSalaryHandler::class));
        static::assertFalse($harness->has(WorkflowPolicy::class));
        static::assertFalse($harness->has(Clock::class));
        static::assertTrue($harness->hasPrivate(Clock::class));
    }

    public function testTheTenantsDefaultToTheKeysOfTheCredentials(): void
    {
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        $harness = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => 'secret-2']]]);

        static::assertSame(['tenant-1', 'tenant-2'], $harness->kernel->getContainer()->getParameter(UrssafTierceDeclarationCesuBundle::PARAMETER_TENANTS));
    }

    public function testTenantsAreStringsEvenWhenTheyLookLikeNumbers(): void
    {
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        $harness = Harness::boot(['credentials' => ['42' => ['client_id' => 'client-42', 'client_secret' => 'secret-42']]]);

        static::assertSame(['tenant-1', '42'], $harness->kernel->getContainer()->getParameter(UrssafTierceDeclarationCesuBundle::PARAMETER_TENANTS));
    }

    public function testTheConfiguredTenantsTakePrecedenceOverTheCredentials(): void
    {
        $harness = Harness::boot(['tenants' => ['tenant-2']]);

        static::assertSame(['tenant-2'], $harness->kernel->getContainer()->getParameter(UrssafTierceDeclarationCesuBundle::PARAMETER_TENANTS));
    }

    public function testTheTokenStoreCanBeProvidedByTheApplication(): void
    {
        $harness = Harness::boot(['token_store' => ['service' => 'test.token_store']]);

        static::assertSame($harness->privateService('test.token_store', TokenStore::class), $harness->privateService(TokenStore::class, TokenStore::class));
    }

    public function testTheApplicationProvidesTheCredentials(): void
    {
        $harness = Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials']);

        static::assertSame($harness->privateService('test.credentials', CredentialsProvider::class), $harness->privateService(CredentialsProvider::class, CredentialsProvider::class));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function process(array $config): array
    {
        $extension = (new UrssafTierceDeclarationCesuBundle())->getContainerExtension();
        static::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        static::assertNotNull($configuration);

        return (new Processor())->processConfiguration($configuration, [$config]);
    }
}
