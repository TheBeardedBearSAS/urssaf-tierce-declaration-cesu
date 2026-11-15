<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

use DateInterval;
use DateTimeZone;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateAiciHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateCesuPlusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\CancelMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ResolveDeclarationOutcomeHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncAllEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\EstimateContributionsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\FindDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetDeclarationHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetEmployerAccountHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Logging\PsrLoggerFailureReporter;

/**
 * Application layer: workflow policy, services and use case handlers (the only public services of the bundle, with
 * the violation list converter and the simulated gateway).
 *
 * Source: reference ApplicationRegistrar, lot 6 design §4.5. The conditional handlers (statistics, outbox) are
 * registered by {@see ServiceRegistrar} and {@see OutboxRegistrar}.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class ApplicationRegistrar
{
    /** Use case handlers exposed to the host application. */
    private const HANDLERS = [
        RegisterEmployerHandler::class,
        ImportEmployerHandler::class,
        RegisterMandateHandler::class,
        CancelMandateHandler::class,
        SyncEmployerStatusHandler::class,
        SyncEmployerStatusesHandler::class,
        SyncAllEmployerStatusesHandler::class,
        ActivateCesuPlusHandler::class,
        ActivateAiciHandler::class,
        DeclareSalaryHandler::class,
        ResolveDeclarationOutcomeHandler::class,
        ReconcileStaleDeclarationsHandler::class,
        RequeueAfterInvalidCredentialsHandler::class,
        VerifyEmployerHandler::class,
        VerifyEmployeeHandler::class,
        GetEmployerAccountHandler::class,
        EstimateContributionsHandler::class,
        GetDeclarationHandler::class,
        FindDeclarationsHandler::class,
    ];

    /** Services shared by the handlers (private, autowired). */
    private const SERVICES = [
        UnitOfWork::class,
        EmployerLocator::class,
        EmployerStatusRefresher::class,
        MandateGuard::class,
        DeclarationLocator::class,
        DeclarationSubmitter::class,
    ];

    private const LOGGER_TAG = 'monolog.logger';
    private const LOGGER_CHANNEL = 'urssaf_cesu';

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $workflow = $config['workflow'];
        $this->services->set(WorkflowPolicy::class)->args([
            new Definition(DateTimeZone::class, [$config['business_timezone']]),
            new Definition(DateInterval::class, [$workflow['stale_submission_after']]),
            $workflow['max_submission_attempts'],
            $workflow['require_local_mandate'],
            $workflow['allow_foster_carer_declarations'],
            $workflow['default_page_size'],
            $workflow['max_page_size'],
        ]);
        $this->services->set(FailureReporter::class, PsrLoggerFailureReporter::class)
            ->args([new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
            ->tag(self::LOGGER_TAG, ['channel' => self::LOGGER_CHANNEL]);
        foreach (self::SERVICES as $service) {
            $this->services->set($service)->autowire();
        }
        foreach (self::HANDLERS as $handler) {
            $this->services->set($handler)->autowire()->public();
        }
    }
}
