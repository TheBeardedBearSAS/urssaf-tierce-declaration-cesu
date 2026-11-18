<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Lock;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command\Declarations;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

/**
 * Every locked command prints the busy line and exits with 0 when another process holds its lock (lot 6 design §7.3).
 */
final class LockedCommandsTest extends TestCase
{
    /**
     * @param array<string, mixed>   $input
     * @param Closure(Harness): void $prepare
     */
    #[DataProvider('lockedCommands')]
    public function testACommandSkipsItsRunWhenTheOperationIsAlreadyRunning(string $name, array $input, string $resource, Closure $prepare): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'test.sink']], false, true);
        $prepare($harness);
        $held = HeldLocks::hold($harness, $resource);
        $requests = \count($harness->client->getRequests());
        $command = $harness->command($name);

        static::assertSame(0, $command->execute($input));

        static::assertSame(\sprintf('Already running (lock "%s" is held): skipped.', $resource), trim($command->getDisplay()));
        static::assertCount($requests, $harness->client->getRequests());
        static::assertTrue($held->isAcquired());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string, Closure(Harness): void}>
     */
    public static function lockedCommands(): iterable
    {
        $none = static function (): void {};

        yield 'sync of one tenant' => [
            'urssaf:cesu:employers:sync-status',
            ['--tenant' => 'tenant-1'],
            'urssaf_cesu.employers.sync-status.tenant-1',
            static function (Harness $harness): void {
                Scenario::mandatedEmployer($harness);
            },
        ];
        yield 'sync of every tenant' => ['urssaf:cesu:employers:sync-status', [], 'urssaf_cesu.employers.sync-status.*', $none];
        yield 'sync of one employer' => [
            'urssaf:cesu:employers:sync-status',
            ['--tenant' => 'tenant-1', '--employer' => Scenario::EMPLOYER],
            'urssaf_cesu.employer.sync-status.employer-1',
            static function (Harness $harness): void {
                Scenario::mandatedEmployer($harness);
            },
        ];
        yield 'reconcile the stale declarations' => [
            'urssaf:cesu:declarations:reconcile-stale',
            [],
            'urssaf_cesu.declarations.reconcile-stale.*',
            static function (Harness $harness): void {
                Scenario::staleDeclaration($harness);
            },
        ];
        yield 'requeue the invalid credentials' => [
            'urssaf:cesu:requeue-invalid-credentials',
            [],
            'urssaf_cesu.requeue-invalid-credentials.tenant-1',
            static function (Harness $harness): void {
                Scenario::invalidCredentialsDeclaration($harness);
            },
        ];
        yield 'relay the outbox' => [
            'urssaf:cesu:outbox:relay',
            [],
            'urssaf_cesu.outbox.relay.*',
            static function (Harness $harness): void {
                Scenario::importedEmployer($harness);
            },
        ];
    }

    public function testTheLocksAreReleasedAndThePendingWorkIsDone(): void
    {
        $harness = Harness::boot([], false, true);
        Scenario::staleDeclaration($harness);
        $command = $harness->command('urssaf:cesu:declarations:reconcile-stale');

        static::assertSame(1, $command->execute([]));
        static::assertSame(0, $command->execute([]));
        static::assertStringContainsString('0 record(s) processed.', $command->getDisplay());

        $held = HeldLocks::hold($harness, 'urssaf_cesu.declarations.reconcile-stale.*');
        static::assertTrue($held->isAcquired());
    }

    public function testADryRunTakesNoLock(): void
    {
        $harness = Harness::boot([], false, true);
        Scenario::staleDeclaration($harness);
        $held = HeldLocks::hold($harness, 'urssaf_cesu.declarations.reconcile-stale.*');
        $command = $harness->command('urssaf:cesu:declarations:reconcile-stale');

        static::assertSame(0, $command->execute(['--dry-run' => true]));

        static::assertStringContainsString('Dry run: 1 record(s) would be processed', $command->getDisplay());
        static::assertTrue($held->isAcquired());
    }

    public function testADryRunOfTheEmployerSyncTakesNoLock(): void
    {
        $harness = Harness::boot([], false, true);
        Scenario::mandatedEmployer($harness);
        $held = HeldLocks::hold($harness, 'urssaf_cesu.employers.sync-status.*');
        $command = $harness->command('urssaf:cesu:employers:sync-status');

        static::assertSame(0, $command->execute(['--dry-run' => true]));

        static::assertStringContainsString('Dry run: 1 record(s) would be processed', $command->getDisplay());
        static::assertTrue($held->isAcquired());
    }

    public function testADryRunOfTheRequeueTakesNoLock(): void
    {
        $harness = Harness::boot([], false, true);
        Scenario::invalidCredentialsDeclaration($harness);
        $held = HeldLocks::hold($harness, 'urssaf_cesu.requeue-invalid-credentials.tenant-1');
        $command = $harness->command('urssaf:cesu:requeue-invalid-credentials');

        static::assertSame(0, $command->execute(['--dry-run' => true]));

        static::assertStringContainsString('Dry run: 1 record(s) would be processed', $command->getDisplay());
        static::assertTrue($held->isAcquired());
    }

    public function testASubmittingDeclarationHeldByNothingIsNotAffected(): void
    {
        $harness = Harness::boot([], false, true);
        Declarations::submittingNow($harness, 'tenant-1', 'declaration-1');
        $command = $harness->command('urssaf:cesu:declarations:reconcile-stale');

        static::assertSame(0, $command->execute([]));
    }
}
