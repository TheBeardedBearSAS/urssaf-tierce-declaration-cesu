<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\ProxyFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

/**
 * The "doctrine" persistence driver: services, mapping, types, named entity manager and an end-to-end declaration
 * (reference DoctrineWiringTest; lot 7 design §8.7).
 */
final class DoctrineWiringTest extends TestCase
{
    public function testTheDoctrineDriverWiresTheRepositoriesTheTransactionManagerAndTheStatistics(): void
    {
        $harness = self::doctrine('default');

        static::assertInstanceOf(DoctrineEmployerAccountRepository::class, $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class));
        static::assertInstanceOf(DoctrineMandateRepository::class, $harness->privateService(MandateRepository::class, MandateRepository::class));
        static::assertInstanceOf(DoctrineDeclarationRecordRepository::class, $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class));
        static::assertInstanceOf(DoctrineTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
        static::assertInstanceOf(DoctrineWorkflowStatistics::class, $harness->privateService(WorkflowStatistics::class, WorkflowStatistics::class));
    }

    public function testTheOutboxIsTheDoctrineOneUnlessAServiceIsGiven(): void
    {
        $doctrine = Harness::boot(['persistence' => ['driver' => 'doctrine'], 'outbox' => ['enabled' => true, 'sink' => 'test.sink']], false, false, true);
        $custom = Harness::boot(['persistence' => ['driver' => 'doctrine'], 'outbox' => ['enabled' => true, 'sink' => 'test.sink', 'service' => 'test.outbox']], false, false, true);

        static::assertInstanceOf(DoctrineOutbox::class, $doctrine->privateService(Outbox::class, Outbox::class));
        static::assertInstanceOf(InMemoryOutbox::class, $custom->privateService(Outbox::class, Outbox::class));
    }

    public function testTheMappingAndTheTypesAreRegisteredInTheDefaultEntityManager(): void
    {
        $harness = self::doctrine('default');
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        static::assertFalse($entityManager->getMetadataFactory()->isTransient(EmployerAccount::class));
        static::assertFalse($entityManager->getMetadataFactory()->isTransient(Mandate::class));
        static::assertFalse($entityManager->getMetadataFactory()->isTransient(DeclarationRecord::class));
        // The bundle leaves the proxy generation mode to the host (doctrine-bundle default outside debug: never); with
        // native lazy objects, no proxy is generated at all and the mode is not configured.
        static::assertSame(
            DoctrineIntegration::nativeLazyObjectsAvailable() ? ProxyFactory::AUTOGENERATE_ALWAYS : ProxyFactory::AUTOGENERATE_NEVER,
            $entityManager->getConfiguration()->getAutoGenerateProxyClasses(),
        );
        if (DoctrineIntegration::nativeLazyObjectsAvailable()) {
            static::assertTrue($entityManager->getConfiguration()->isNativeLazyObjectsEnabled());
        }
        foreach (array_keys(DoctrineIntegration::types()) as $type) {
            static::assertTrue(Type::hasType($type), $type);
        }
    }

    public function testAnotherEntityManagerCanBeSelected(): void
    {
        $harness = Harness::boot(
            ['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']],
            false,
            false,
            [
                'dbal' => ['connections' => ['default' => ['driver' => 'pdo_sqlite', 'memory' => true], 'urssaf' => ['driver' => 'pdo_sqlite', 'memory' => true]]],
                'orm' => [
                    'enable_lazy_ghost_objects' => true,
                    'entity_managers' => ['default' => ['connection' => 'default'], 'urssaf' => ['connection' => 'urssaf']],
                ],
            ],
        );

        $mapped = $harness->privateService('doctrine.orm.urssaf_entity_manager', EntityManagerInterface::class);
        $other = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);
        static::assertFalse($mapped->getMetadataFactory()->isTransient(EmployerAccount::class));
        static::assertTrue($other->getMetadataFactory()->isTransient(EmployerAccount::class));
        static::assertInstanceOf(DoctrineEmployerAccountRepository::class, $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class));
    }

    public function testNothingIsMappedWhenAnotherDriverIsSelected(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'in_memory']], false, false, true);
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        static::assertTrue($entityManager->getMetadataFactory()->isTransient(EmployerAccount::class));
        static::assertInstanceOf(InMemoryEmployerAccountRepository::class, $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class));
        static::assertInstanceOf(InMemoryMandateRepository::class, $harness->privateService(MandateRepository::class, MandateRepository::class));
        static::assertInstanceOf(ImmediateTransactionManager::class, $harness->privateService(TransactionManager::class, TransactionManager::class));
    }

    public function testTheProxyCacheWarmerGeneratesTheProxiesOfTheMappedEntitiesWhichThenLoadLazily(): void
    {
        $native = DoctrineIntegration::nativeLazyObjectsAvailable();
        $harness = Harness::boot(
            ['persistence' => ['driver' => 'doctrine']],
            false,
            false,
            ['dbal' => ['driver' => 'pdo_sqlite', 'memory' => true], 'orm' => ['enable_lazy_ghost_objects' => true, 'auto_generate_proxy_classes' => false]],
        );
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);
        if (!$native) {
            $this->assertProxiesAreWarmedUp($harness, $entityManager);
        }
        $harness->createDatabaseSchema();
        $account = Scenario::importedEmployer($harness);
        $entityManager->clear();

        $reference = $entityManager->getReference(EmployerAccount::class, EmployerAccountId::fromString($account->accountId));

        static::assertInstanceOf(EmployerAccount::class, $reference);
        static::assertTrue($entityManager->getUnitOfWork()->isUninitializedObject($reference));
        static::assertSame(Scenario::EMPLOYER, $reference->externalReference()->value());
        static::assertFalse($entityManager->getUnitOfWork()->isUninitializedObject($reference));
    }

    public function testADeclarationIsPersistedEndToEnd(): void
    {
        $harness = self::doctrine('default');
        $harness->createDatabaseSchema();
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);

        $submission = Scenario::declaration($harness);
        $entityManager->clear();

        static::assertSame('declared', $submission->declaration->status->state);
        $record = $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class)
            ->findByExternalReference(TenantId::fromString(Scenario::TENANT), ExternalReference::fromString(Scenario::DECLARATION));
        static::assertNotNull($record);
        static::assertSame(DeclarationState::Declared, $record->state());
        $overview = $harness->get(GetWorkflowOverviewHandler::class)(GetWorkflowOverview::forTenant(TenantId::fromString(Scenario::TENANT)))[0];
        static::assertSame(1, $overview->declarations['declared']);
        static::assertSame(1, $overview->mandates['notified']);
    }

    private function assertProxiesAreWarmedUp(Harness $harness, EntityManagerInterface $entityManager): void
    {
        $proxyDirectory = $entityManager->getConfiguration()->getProxyDir();
        static::assertIsString($proxyDirectory);
        (new Filesystem())->remove($proxyDirectory);

        $harness->privateService('cache_warmer', CacheWarmerAggregate::class)->warmUp($harness->kernel->getCacheDir());

        $files = glob($proxyDirectory . '/*.php');
        static::assertIsArray($files);
        $proxies = array_map(basename(...), $files);
        sort($proxies);
        static::assertSame(
            [
                '__CG__TheBeardedBearUrssafTierceDeclarationCesuBridgeDoctrineEntityActiveMandate.php',
                '__CG__TheBeardedBearUrssafTierceDeclarationCesuBridgeDoctrineEntityOutboxMessage.php',
                '__CG__TheBeardedBearUrssafTierceDeclarationCesuDomainDeclarationDeclarationRecord.php',
                '__CG__TheBeardedBearUrssafTierceDeclarationCesuDomainEmployerEmployerAccount.php',
                '__CG__TheBeardedBearUrssafTierceDeclarationCesuDomainMandateMandate.php',
            ],
            $proxies,
        );
    }

    private static function doctrine(string $entityManager): Harness
    {
        return Harness::boot(['persistence' => ['driver' => 'doctrine', 'entity_manager' => $entityManager]], false, false, true);
    }
}
