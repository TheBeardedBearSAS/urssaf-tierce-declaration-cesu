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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

final class ReconcileStaleDeclarationsCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:declarations:reconcile-stale';

    public function testAStaleDeclarationIsMovedToManualReviewAndNeverResent(): void
    {
        $harness = Harness::boot();
        $id = Scenario::staleDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute([]));

        static::assertSame([['Record', 'Outcome', 'Codes'], [$id->value(), 'manual_review', 'stale_submission']], \array_slice(Output::rows($command->getDisplay()), 0, 2));
        static::assertStringContainsString('1 record(s) processed (manual_review: 1).', $command->getDisplay());
        static::assertSame(DeclarationState::OutcomeUnknown, $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class)->get($id)->status()->state());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testNothingIsDoneWhenNoDeclarationIsStale(): void
    {
        $harness = Harness::boot();
        Declarations::submittingNow($harness, 'tenant-1', 'declaration-1');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--limit' => '5']));

        static::assertStringContainsString('0 record(s) processed.', $command->getDisplay());
    }

    public function testTheLimitCapsTheDeclarationsReconciled(): void
    {
        $harness = Harness::boot();
        Scenario::staleDeclaration($harness, 'tenant-1', 'declaration-1');
        Scenario::staleDeclaration($harness, 'tenant-1', 'declaration-2');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--limit' => '1']));

        static::assertStringContainsString('1 record(s) processed (manual_review: 1).', $command->getDisplay());
    }

    public function testADryRunListsOnlyTheStaleDeclarationsAndChangesNothing(): void
    {
        $harness = Harness::boot();
        $stale = Scenario::staleDeclaration($harness, 'tenant-1', 'declaration-1');
        Declarations::submittingNow($harness, 'tenant-1', 'declaration-2');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--dry-run' => true]));

        static::assertSame([['Tenant', 'Record'], ['tenant-1', $stale->value()]], Output::rows($command->getDisplay()));
        static::assertStringContainsString('Dry run: 1 record(s) would be processed, URSSAF was not called.', $command->getDisplay());
        static::assertSame(DeclarationState::Submitting, $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class)->get($stale)->status()->state());
    }

    public function testADryRunHonoursTheLimit(): void
    {
        $harness = Harness::boot();
        Scenario::staleDeclaration($harness, 'tenant-1', 'declaration-1');
        Scenario::staleDeclaration($harness, 'tenant-1', 'declaration-2');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--dry-run' => true, '--limit' => '1']));

        static::assertStringContainsString('Dry run: 1 record(s)', $command->getDisplay());
    }

    public function testAnInvalidLimitExitsWithTwo(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['--limit' => '-3']));

        static::assertStringContainsString('The --limit option must be a positive integer.', $command->getDisplay());
    }
}
