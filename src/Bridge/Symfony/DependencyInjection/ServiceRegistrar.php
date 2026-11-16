<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\ConstraintViolationList;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\WorkflowStatusCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Validation\ViolationListConverter;

/**
 * Declares the services of the bundle from its processed configuration. Services are private (the default of the
 * container configurator), except the use case handlers (the entry points of the host application), the simulated
 * gateway (tests) and the violation list converter (registered when symfony/validator is installed).
 *
 * Source: reference ServiceRegistrar, lot 6 design §2.1 and §4.3.
 *
 * @phpstan-type Config array{
 *     environment: string,
 *     base_uri: ?string,
 *     request_id_header: ?string,
 *     application_id_header: array{name: ?string, value: ?string},
 *     agreement_encoding: string,
 *     token_safety_margin: int,
 *     business_timezone: string,
 *     http_client: ?string,
 *     http_timeout: float,
 *     credentials_provider: ?string,
 *     credentials: array<string, array{client_id: string, client_secret: string}>,
 *     tenants: list<string>,
 *     token_store: array{cache_pool: string, service: ?string},
 *     gateway: array{simulated: bool},
 *     rate_limiter: array{enabled: bool, limit: int, window: int},
 *     lock: array{enabled: bool|string, factory: string, ttl: float},
 *     outbox: array{enabled: bool, service: ?string, sink: string, bus: string, retention: string},
 *     scheduler: array{enabled: bool, name: string, frequencies: array<string, ?string>},
 *     workflow: array{stale_submission_after: string, max_submission_attempts: int, require_local_mandate: bool, allow_foster_carer_declarations: bool, default_page_size: int, max_page_size: int, erasure: array{allow_unknown_outcomes: bool, allow_active_mandate: bool}},
 *     persistence: array{driver: string, employer_account_repository: ?string, mandate_repository: ?string, declaration_record_repository: ?string, transaction_manager: ?string, workflow_statistics: ?string, entity_manager: string},
 *     messenger: array{enabled: bool, bus: string}
 * }
 */
final readonly class ServiceRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        (new InfrastructureRegistrar($this->services))->register($config);
        (new GatewayRegistrar($this->services))->register($config);
        (new PersistenceRegistrar($this->services))->register($config['persistence']);
        (new LockRegistrar($this->services))->register($config['lock']);
        (new ApplicationRegistrar($this->services))->register($config);
        $entryPoints = new EntryPointRegistrar($this->services);
        $entryPoints->registerConsole($config);
        (new DiagnosticRegistrar($this->services))->register($config);
        if (PersistenceRegistrar::hasStatistics($config['persistence']['driver'], $config['persistence']['workflow_statistics'])) {
            $this->services->set(GetWorkflowOverviewHandler::class)->autowire()->public();
            $this->services->set(WorkflowStatusCommand::class)->autowire()->tag('console.command');
        }
        if ($config['messenger']['enabled']) {
            $entryPoints->registerMessenger();
        }
        if ($config['outbox']['enabled']) {
            (new OutboxRegistrar($this->services))->register($config);
        }
        if ($config['scheduler']['enabled']) {
            $this->services->set(UrssafScheduleProvider::class)
                ->args([$config['outbox']['enabled']
                    ? $config['scheduler']['frequencies']
                    // Without the outbox, its commands do not exist.
                    : [...$config['scheduler']['frequencies'], 'relay_outbox' => null, 'purge_outbox' => null]])
                ->tag('scheduler.schedule_provider', ['name' => $config['scheduler']['name']]);
        }
        if (class_exists(ConstraintViolationList::class)) {
            $this->services->set(ViolationListConverter::class)
                ->args([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->public();
        }
    }
}
