<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use DateInterval;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Definition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;

/**
 * Every option of the configuration reaches its service (lot 6 design §4.7).
 */
final class BundleConfigurationOptionsTest extends TestCase
{
    public function testTheApiConfigurationReceivesEveryOption(): void
    {
        $harness = Harness::boot([
            'environment' => 'production',
            'base_uri' => 'https://proxy.example.test',
            'request_id_header' => 'X-Request-Id',
            'application_id_header' => ['name' => 'X-Application', 'value' => 'my-application'],
            'agreement_encoding' => 'numeric',
            'token_safety_margin' => 120,
            'business_timezone' => 'America/Martinique',
        ]);
        $api = $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);

        static::assertSame(Environment::Production, $api->environment());
        static::assertSame('https://proxy.example.test', $api->baseUri());
        static::assertSame('X-Request-Id', $api->requestIdHeaderName());
        $applicationId = $api->applicationIdHeader();
        static::assertNotNull($applicationId);
        static::assertSame('X-Application', $applicationId->name());
        static::assertSame('my-application', $applicationId->value());
        static::assertSame(AgreementEncoding::Numeric, $api->agreementEncoding());
        static::assertSame(120, $api->tokenSafetyMarginSeconds());
        static::assertSame('America/Martinique', $api->businessTimeZone()->getName());
    }

    public function testTheWorkflowPolicyReceivesEveryOption(): void
    {
        $harness = Harness::boot([
            'business_timezone' => 'America/Martinique',
            'workflow' => [
                'stale_submission_after' => 'PT5M',
                'max_submission_attempts' => 3,
                'require_local_mandate' => false,
                'allow_foster_carer_declarations' => true,
                'default_page_size' => 20,
                'max_page_size' => 50,
                'erasure' => ['allow_unknown_outcomes' => true, 'allow_active_mandate' => true],
            ],
        ]);
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);

        static::assertSame('America/Martinique', $policy->businessTimeZone()->getName());
        static::assertSame(5, $policy->staleSubmissionAfter()->i);
        static::assertSame(3, $policy->maxSubmissionAttempts());
        static::assertFalse($policy->requireLocalMandate());
        static::assertTrue($policy->allowFosterCarerDeclarations());
        static::assertSame(20, $policy->pageSize(null));
        static::assertSame(50, $policy->pageSize(500));
        static::assertTrue($policy->allowErasureWithUnknownOutcomes());
        static::assertTrue($policy->allowErasureWithActiveMandate());
    }

    public function testTheErasureDefaultsAreStrict(): void
    {
        $policy = Harness::boot()->privateService(WorkflowPolicy::class, WorkflowPolicy::class);

        static::assertFalse($policy->allowErasureWithUnknownOutcomes());
        static::assertFalse($policy->allowErasureWithActiveMandate());
    }

    public function testEachErasureOptionIsPassedToItsOwnPolicyValue(): void
    {
        $unknown = Harness::boot(['workflow' => ['erasure' => ['allow_unknown_outcomes' => true]]])->privateService(WorkflowPolicy::class, WorkflowPolicy::class);
        $active = Harness::boot(['workflow' => ['erasure' => ['allow_active_mandate' => true]]])->privateService(WorkflowPolicy::class, WorkflowPolicy::class);

        static::assertTrue($unknown->allowErasureWithUnknownOutcomes());
        static::assertFalse($unknown->allowErasureWithActiveMandate());
        static::assertFalse($active->allowErasureWithUnknownOutcomes());
        static::assertTrue($active->allowErasureWithActiveMandate());
    }

    public function testTheSmallestLimitsAreAccepted(): void
    {
        $harness = Harness::boot([
            'token_safety_margin' => 0,
            'http_timeout' => 0.1,
            'workflow' => ['default_page_size' => 1, 'max_page_size' => 1, 'max_submission_attempts' => 1],
            'rate_limiter' => ['limit' => 1, 'window' => 1],
            'lock' => ['ttl' => 1.0],
        ]);
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);

        static::assertSame(0, $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class)->tokenSafetyMarginSeconds());
        static::assertSame(1, $policy->pageSize(5));
        static::assertSame(1, $policy->maxSubmissionAttempts());
    }

    public function testTheRateLimiterEnforcesTheConfiguredLimitPerTenant(): void
    {
        $harness = Harness::boot(['rate_limiter' => ['enabled' => true, 'limit' => 2, 'window' => 600]]);
        $limiter = $harness->privateService(RateLimiter::class, RateLimiter::class);
        $tenant = TenantId::fromString('tenant-1');

        static::assertInstanceOf(SlidingWindowRateLimiter::class, $limiter);
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $limiter->acquire($tenant, Operation::VerifyEmployer);
        $limiter->acquire(TenantId::fromString('tenant-2'), Operation::VerifyEmployer);
        try {
            $limiter->acquire($tenant, Operation::VerifyEmployer);
            static::fail('The third call within the window must be refused.');
        } catch (RateLimitExceeded $exceeded) {
            static::assertSame(600, $exceeded->retryAfterSeconds());
        }
    }

    public function testTheRateLimiterWindowIsConfigurable(): void
    {
        $harness = Harness::boot(['rate_limiter' => ['enabled' => true, 'limit' => 1, 'window' => 30]]);
        $limiter = $harness->privateService(RateLimiter::class, RateLimiter::class);
        $tenant = TenantId::fromString('tenant-1');
        $limiter->acquire($tenant, Operation::VerifyEmployer);

        $this->expectException(RateLimitExceeded::class);

        $limiter->acquire($tenant, Operation::VerifyEmployer);
    }

    public function testTheLockModesAndTheSmallestTtlAreAccepted(): void
    {
        foreach (['auto', true, false] as $mode) {
            $harness = Harness::boot(['lock' => ['enabled' => $mode, 'ttl' => 1.0]], false, true);

            static::assertTrue($harness->hasPrivate(OperationLocks::class));
        }
    }

    public function testTheLockTtlReachesTheLocks(): void
    {
        $definition = (new TestKernel(['lock' => ['ttl' => 120.5]], false, true))->definitions()->getDefinition(OperationLocks::class);

        static::assertSame(120.5, $definition->getArgument(1));
    }

    public function testThePurgeCommandKeepsTheConfiguredRetention(): void
    {
        $container = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher', 'retention' => 'P7D']]))->definitions();

        static::assertEquals(new Definition(DateInterval::class, ['P7D']), $container->getDefinition(PurgeOutboxCommand::class)->getArgument(1));
    }
}
