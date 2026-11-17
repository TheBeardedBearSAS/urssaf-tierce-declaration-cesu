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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

final class PurgeOutboxCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:outbox:purge';

    public function testTheEntriesDispatchedForLongerThanTheRetentionAreDeleted(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'test.sink', 'retention' => 'P2D']]);
        Scenario::importedEmployer($harness);
        $harness->get(RelayOutboxHandler::class)(new RelayOutbox(null));
        $command = $harness->command(self::NAME);

        $harness->clock->modify('+1 day');
        static::assertSame(0, $command->execute([]));
        static::assertStringContainsString('0 dispatched outbox entries deleted.', $command->getDisplay());

        $harness->clock->modify('+2 days');
        static::assertSame(0, $command->execute([]));
        static::assertStringContainsString('1 dispatched outbox entries deleted.', $command->getDisplay());
    }
}
