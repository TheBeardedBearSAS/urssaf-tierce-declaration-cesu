<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CancelMandateCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\EstimateContributionsCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\ForgetEmployerCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\ImportEmployerCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\ReconcileStaleDeclarationsCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\RegisterMandateCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\RequeueInvalidCredentialsCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\ResolveDeclarationOutcomeCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\SyncEmployerStatusesCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\VerifyEmployeeCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\VerifyEmployerCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler\SyncEmployerStatusesMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler\SyncEmployerStatusMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\UrssafTierceDeclarationCesuBundle;

/**
 * Driving adapters: console commands and Messenger handlers (no auto-submit listener, ADR 0013).
 *
 * Source: reference EntryPointRegistrar, lot 6 design §4.6.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class EntryPointRegistrar
{
    private const COMMANDS = [
        ReconcileStaleDeclarationsCommand::class,
    ];

    /** Commands that fall back on the configured tenants. */
    private const TENANT_COMMANDS = [
        VerifyEmployerCommand::class,
        VerifyEmployeeCommand::class,
        EstimateContributionsCommand::class,
        ImportEmployerCommand::class,
        RegisterMandateCommand::class,
        CancelMandateCommand::class,
        ForgetEmployerCommand::class,
        ResolveDeclarationOutcomeCommand::class,
        RequeueInvalidCredentialsCommand::class,
    ];

    private const MESSAGE_HANDLERS = [
        SyncEmployerStatusesMessageHandler::class,
        SyncEmployerStatusMessageHandler::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function registerConsole(array $config): void
    {
        foreach (self::COMMANDS as $command) {
            $this->services->set($command)->autowire()->tag('console.command');
        }
        foreach (self::TENANT_COMMANDS as $command) {
            $this->services->set($command)
                ->autowire()
                ->arg('$configuredTenants', '%' . UrssafTierceDeclarationCesuBundle::PARAMETER_TENANTS . '%')
                ->tag('console.command');
        }
        $this->services->set(SyncEmployerStatusesCommand::class)
            ->autowire()
            ->arg('$bus', $config['messenger']['enabled'] ? new Reference($config['messenger']['bus']) : null)
            ->tag('console.command');
    }

    public function registerMessenger(): void
    {
        foreach (self::MESSAGE_HANDLERS as $handler) {
            $this->services->set($handler)->autowire()->tag('messenger.message_handler');
        }
    }
}
