<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\PersistenceRegistrar;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Transaction\ImmediateTransactionManager;

/**
 * The services of each persistence driver, registered into a bare container (no kernel, no shared compilation).
 */
final class PersistenceRegistrarTest extends TestCase
{
    public function testTheDoctrineDriverGivesTheConfiguredEntityManagerToEveryService(): void
    {
        $container = self::register(['driver' => 'doctrine', 'entity_manager' => 'urssaf']);

        $expected = [
            EmployerAccountRepository::class => DoctrineEmployerAccountRepository::class,
            MandateRepository::class => DoctrineMandateRepository::class,
            DeclarationRecordRepository::class => DoctrineDeclarationRecordRepository::class,
            TransactionManager::class => DoctrineTransactionManager::class,
            WorkflowStatistics::class => DoctrineWorkflowStatistics::class,
        ];
        static::assertSame(array_keys($expected), self::ids($container));
        foreach ($expected as $id => $class) {
            $definition = $container->getDefinition($id);
            static::assertSame($class, $definition->getClass(), $id);
            static::assertEquals([new Reference('doctrine.orm.urssaf_entity_manager')], $definition->getArguments(), $id);
        }
    }

    public function testTheInMemoryDriverRegistersTheInMemoryServices(): void
    {
        $container = self::register(['driver' => 'in_memory']);

        $expected = [
            EmployerAccountRepository::class => InMemoryEmployerAccountRepository::class,
            MandateRepository::class => InMemoryMandateRepository::class,
            DeclarationRecordRepository::class => InMemoryDeclarationRecordRepository::class,
            TransactionManager::class => ImmediateTransactionManager::class,
            WorkflowStatistics::class => InMemoryWorkflowStatistics::class,
        ];
        static::assertSame(array_keys($expected), self::ids($container));
        foreach ($expected as $id => $class) {
            static::assertSame($class, $container->getDefinition($id)->getClass(), $id);
        }
        static::assertEquals(
            [new Reference(EmployerAccountRepository::class), new Reference(MandateRepository::class), new Reference(DeclarationRecordRepository::class)],
            $container->getDefinition(WorkflowStatistics::class)->getArguments(),
        );
    }

    public function testTheCustomDriverAliasesTheGivenServices(): void
    {
        $container = self::register([
            'driver' => 'custom',
            'employer_account_repository' => 'my.employers',
            'mandate_repository' => 'my.mandates',
            'declaration_record_repository' => 'my.declarations',
            'transaction_manager' => 'my.transactions',
            'workflow_statistics' => 'my.statistics',
        ]);

        static::assertSame([], self::ids($container));
        static::assertSame('my.employers', (string) $container->getAlias(EmployerAccountRepository::class));
        static::assertSame('my.mandates', (string) $container->getAlias(MandateRepository::class));
        static::assertSame('my.declarations', (string) $container->getAlias(DeclarationRecordRepository::class));
        static::assertSame('my.transactions', (string) $container->getAlias(TransactionManager::class));
        static::assertSame('my.statistics', (string) $container->getAlias(WorkflowStatistics::class));
    }

    public function testTheCustomDriverFallsBackToTheImmediateTransactionManagerAndHasNoStatistics(): void
    {
        $container = self::register([
            'driver' => 'custom',
            'employer_account_repository' => 'my.employers',
            'mandate_repository' => 'my.mandates',
            'declaration_record_repository' => 'my.declarations',
        ]);

        static::assertSame(ImmediateTransactionManager::class, $container->getDefinition(TransactionManager::class)->getClass());
        static::assertFalse($container->hasAlias(TransactionManager::class));
        static::assertFalse($container->hasAlias(WorkflowStatistics::class));
        static::assertFalse($container->hasDefinition(WorkflowStatistics::class));
    }

    /**
     * @return list<string>
     */
    private static function ids(ContainerBuilder $container): array
    {
        return array_values(array_diff(array_keys($container->getDefinitions()), ['service_container']));
    }

    /**
     * @param array{driver: string, employer_account_repository?: string, mandate_repository?: string, declaration_record_repository?: string, transaction_manager?: string, workflow_statistics?: string, entity_manager?: string} $persistence
     */
    private static function register(array $persistence): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__);

        (new PersistenceRegistrar($configurator->services()))->register([
            'driver' => $persistence['driver'],
            'employer_account_repository' => $persistence['employer_account_repository'] ?? null,
            'mandate_repository' => $persistence['mandate_repository'] ?? null,
            'declaration_record_repository' => $persistence['declaration_record_repository'] ?? null,
            'transaction_manager' => $persistence['transaction_manager'] ?? null,
            'workflow_statistics' => $persistence['workflow_statistics'] ?? null,
            'entity_manager' => $persistence['entity_manager'] ?? 'default',
        ]);

        return $container;
    }
}
