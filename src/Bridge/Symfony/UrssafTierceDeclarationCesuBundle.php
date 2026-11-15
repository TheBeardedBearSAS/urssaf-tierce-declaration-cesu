<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\BundleConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\ServiceRegistrar;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\InstallationCheck;

/**
 * Symfony integration of the URSSAF "API Tierce Déclaration CESU" client (Symfony 6.4 and 7.x).
 *
 * Source: reference bundle (UrssafTiersPrestationsBundle), lot 6 design §4.1, ADR 0013.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final class UrssafTierceDeclarationCesuBundle extends AbstractBundle
{
    public const ALIAS = 'urssaf_tierce_declaration_cesu';
    public const PARAMETER_TENANTS = 'urssaf_tierce_declaration_cesu.tenants';

    protected string $extensionAlias = self::ALIAS;

    /**
     * The bundle directory ({@see AbstractBundle} assumes a class one level below it), so that the framework finds the
     * shipped translations in Resources/translations.
     */
    public function getPath(): string
    {
        return __DIR__;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        BundleConfiguration::define($definition);
    }

    /**
     * @param array<array-key, mixed> $config the configuration validated by {@see BundleConfiguration}
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        /** @var Config $validated */
        $validated = $config;
        $tenants = $validated['tenants'] !== [] ? $validated['tenants'] : array_map(strval(...), array_keys($validated['credentials']));
        $configurator->parameters()->set(self::PARAMETER_TENANTS, $tenants);
        (new ServiceRegistrar($configurator->services()))->register($validated);
        $container->registerForAutoconfiguration(InstallationCheck::class)->addTag(InstallationCheck::TAG);
    }
}
