<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\AuthenticationCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\ConfigurationCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\InstallationCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\LockCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\PersistenceCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\UrssafTierceDeclarationCesuBundle;

/**
 * The "urssaf:cesu:check" command and its checks, in report order (registration order, host checks afterwards unless
 * they have a positive tag priority).
 *
 * The configuration and the token provider reach their checks through closures, so that an invalid configuration is
 * reported by the "configuration" check instead of preventing the command from running.
 *
 * Source: reference DiagnosticRegistrar, lot 6 design §6.2, ADR 0013.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class DiagnosticRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->check(ConfigurationCheck::class, [new ServiceClosureArgument(new Reference(ServiceIds::API_CONFIGURATION))]);
        $this->check(CredentialsCheck::class, [new Reference(CredentialsProvider::class)]);
        [$gatewayCheck, $gatewayArguments] = $config['gateway']['simulated']
            ? [SimulatedGatewayCheck::class, []]
            : [AuthenticationCheck::class, [new Reference(CredentialsProvider::class), new ServiceClosureArgument(new Reference(ServiceIds::PROBE_TOKEN_PROVIDER))]];
        $this->check($gatewayCheck, $gatewayArguments);
        $this->check(TokenStoreCheck::class, [$config['token_store']['service'], $config['token_store']['cache_pool']]);
        $this->check(PersistenceCheck::class, [$config['persistence']['driver']]);
        $this->check(LockCheck::class, [new Reference(OperationLocks::class)]);

        $this->services->set(CheckInstallationCommand::class)
            ->args([new TaggedIteratorArgument(InstallationCheck::TAG), '%' . UrssafTierceDeclarationCesuBundle::PARAMETER_TENANTS . '%'])
            ->tag('console.command');
    }

    /**
     * @param class-string<InstallationCheck> $class
     * @param list<mixed>                     $arguments
     */
    private function check(string $class, array $arguments): void
    {
        $this->services->set($class)->args($arguments)->tag(InstallationCheck::TAG);
    }
}
