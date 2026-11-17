<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;

final class RelayOutboxCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:outbox:relay';
    private const ENABLED = ['outbox' => ['enabled' => true, 'sink' => 'test.sink']];

    public function testThePendingEventsAreDelivered(): void
    {
        $harness = Harness::boot(self::ENABLED);
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertSame([['Dispatched', 'Failed', 'Deferred'], ['1', '0', '0']], Output::rows($command->getDisplay()));
        static::assertCount(1, $harness->privateService('test.sink', RecordingIntegrationEventSink::class)->deliveredIds());
    }

    public function testAFailedDeliveryMakesTheCommandExitWithOne(): void
    {
        $harness = Harness::boot(self::ENABLED);
        $harness->privateService(Outbox::class, InMemoryOutbox::class)->append(
            new EmployerRegistered('e1', '2026-11-15T10:00:00Z', 'tenant-1', 'refused-aggregate', 'employer-1', 'Z0003752390009', 'imported'),
        );
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--limit' => '5']));

        static::assertSame([['Dispatched', 'Failed', 'Deferred'], ['0', '1', '0']], Output::rows($command->getDisplay()));
    }

    public function testAnInvalidLimitExitsWithTwo(): void
    {
        $command = Harness::boot(self::ENABLED)->command(self::NAME);

        static::assertSame(2, $command->execute(['--limit' => '0']));
    }
}
