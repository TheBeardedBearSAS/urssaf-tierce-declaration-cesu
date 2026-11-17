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

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Standalone\UrssafTierceDeclarationCesu;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;

/**
 * Every use case handler of the Application layer is a public service of the bundle (lot 5 open point 2), exactly the
 * ones that the standalone facade exposes.
 */
final class HandlersRegistrationTest extends TestCase
{
    public function testEveryHandlerIsAPublicServiceWithTheOutboxEnabled(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]);

        $handlers = self::handlersOfTheApplicationLayer();
        static::assertCount(23, $handlers);
        foreach ($handlers as $handler) {
            static::assertTrue($harness->has($handler), $handler);
            static::assertInstanceOf($handler, $harness->get($handler));
        }
    }

    public function testTheHandlersAreTheOnesOfTheStandaloneFacade(): void
    {
        $returned = [];
        foreach ((new ReflectionClass(UrssafTierceDeclarationCesu::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if ($method->isConstructor() || $method->getName() === 'gateway' || !$type instanceof ReflectionNamedType) {
                continue;
            }
            $returned[] = $type->getName();
        }
        $registered = self::handlersOfTheApplicationLayer();
        sort($returned);
        sort($registered);

        static::assertSame($registered, $returned);
    }

    public function testTheOutboxHandlersAreAbsentWithoutTheOutbox(): void
    {
        $harness = Harness::boot();

        static::assertFalse($harness->has(RelayOutboxHandler::class));
        static::assertFalse($harness->has(PurgeOutboxHandler::class));
        static::assertTrue($harness->has(GetWorkflowOverviewHandler::class));
    }

    public function testTheOverviewIsAbsentWithACustomDriverWithoutStatistics(): void
    {
        $harness = Harness::boot([
            'persistence' => [
                'driver' => 'custom',
                'employer_account_repository' => 'test.accounts',
                'mandate_repository' => 'test.mandates',
                'declaration_record_repository' => 'test.declarations',
            ],
        ]);

        static::assertFalse($harness->has(GetWorkflowOverviewHandler::class));
        static::assertNotContains('urssaf:cesu:status', self::commands($harness));
    }

    public function testTheOverviewIsRegisteredWithACustomDriverWithStatistics(): void
    {
        $harness = Harness::boot([
            'persistence' => [
                'driver' => 'custom',
                'employer_account_repository' => 'test.accounts',
                'mandate_repository' => 'test.mandates',
                'declaration_record_repository' => 'test.declarations',
                'workflow_statistics' => 'test.statistics',
            ],
        ]);

        static::assertTrue($harness->has(GetWorkflowOverviewHandler::class));
        static::assertContains('urssaf:cesu:status', self::commands($harness));
    }

    /**
     * @return list<string>
     */
    private static function commands(Harness $harness): array
    {
        return array_map(strval(...), array_keys((new Application($harness->kernel))->all()));
    }

    /**
     * @return list<class-string>
     */
    private static function handlersOfTheApplicationLayer(): array
    {
        $handlers = [];
        foreach (['Command', 'Query'] as $directory) {
            $files = glob(\dirname(__DIR__, 3) . '/src/Application/' . $directory . '/*Handler.php');
            \assert(\is_array($files), 'The handlers directory exists.');
            foreach ($files as $file) {
                $class = 'TheBeardedBear\UrssafTierceDeclarationCesu\Application\\' . $directory . '\\' . basename($file, '.php');
                \assert(class_exists($class), 'Every handler file declares its class.');
                $handlers[] = $class;
            }
        }

        return $handlers;
    }
}
