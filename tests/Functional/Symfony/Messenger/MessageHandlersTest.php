<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message\SyncEmployerStatusMessage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler\SyncEmployerStatusesMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler\SyncEmployerStatusMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\RetryDelay;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * The two Messenger handlers over the in-memory fakes of the application layer (no kernel): what they run, under which
 * lock, and how failures reach Messenger (lot 6 design 7.2 and 7.3).
 */
final class MessageHandlersTest extends TestCase
{
    public function testTheTenantHandlerRefreshesTheEmployersOfTheTenantOnly(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->account(2);
        $harness->account(3, EmployerStage::Registered, Harness::OTHER_TENANT);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));

        self::tenantHandler($harness, new OperationLocks(null))(new SyncEmployerStatusesMessage(Harness::TENANT));

        static::assertSame([Harness::TENANT, Harness::TENANT], $harness->gateway->tenants());
        static::assertSame(Harness::NOW, $harness->accounts->get(EmployerAccountId::fromString('account-1'))->lastCheckedAt()?->toIso8601());
        static::assertSame(Harness::NOW, $harness->accounts->get(EmployerAccountId::fromString('account-2'))->lastCheckedAt()?->toIso8601());
        static::assertNull($harness->accounts->get(EmployerAccountId::fromString('account-3'))->lastCheckedAt());
    }

    public function testTheAccountHandlerRefreshesOneEmployer(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->account(2);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));

        self::accountHandler($harness, new OperationLocks(null))(new SyncEmployerStatusMessage(Harness::TENANT, 'account-2'));

        static::assertSame('call:020', $harness->journal->entries()[0]);
        static::assertNull($harness->accounts->get(EmployerAccountId::fromString('account-1'))->lastCheckedAt());
        static::assertSame(Harness::NOW, $harness->accounts->get(EmployerAccountId::fromString('account-2'))->lastCheckedAt()?->toIso8601());
        static::assertSame([null], array_map(static fn(EmployerVerification $verification): ?EmployeeReference => $verification->employee(), $harness->gateway->verifications()));
    }

    public function testTheLockIsReleasedAfterTheRun(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $harness = new Harness();
        $harness->account(1);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));
        $locks = new OperationLocks($factory);

        self::tenantHandler($harness, $locks)(new SyncEmployerStatusesMessage(Harness::TENANT));
        self::accountHandler($harness, $locks)(new SyncEmployerStatusMessage(Harness::TENANT, 'account-1'));

        static::assertTrue($factory->createLock('urssaf_cesu.employers.sync-status.tenant-1')->acquire());
        static::assertTrue($factory->createLock('urssaf_cesu.employer.sync-status.account-1')->acquire());
    }

    public function testTheTenantHandlerAsksForARetryWhileTheTenantIsBeingRefreshed(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $held = $factory->createLock('urssaf_cesu.employers.sync-status.tenant-1');
        static::assertTrue($held->acquire());
        $harness = new Harness();
        $harness->account(1);

        try {
            self::tenantHandler($harness, new OperationLocks($factory))(new SyncEmployerStatusesMessage(Harness::TENANT));
            static::fail('The handler must ask for a retry.');
        } catch (RecoverableMessageHandlingException $retry) {
            RetryDelay::assertCarries(30_000, $retry);
            static::assertStringContainsString('lock "urssaf_cesu.employers.sync-status.tenant-1" is held', $retry->getMessage());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testTheAccountHandlerAsksForARetryWhileTheAccountIsBeingRefreshed(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $held = $factory->createLock('urssaf_cesu.employer.sync-status.account-1');
        static::assertTrue($held->acquire());
        $harness = new Harness();
        $harness->account(1);

        try {
            self::accountHandler($harness, new OperationLocks($factory))(new SyncEmployerStatusMessage(Harness::TENANT, 'account-1'));
            static::fail('The handler must ask for a retry.');
        } catch (RecoverableMessageHandlingException $retry) {
            RetryDelay::assertCarries(30_000, $retry);
            static::assertStringContainsString('lock "urssaf_cesu.employer.sync-status.account-1" is held', $retry->getMessage());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testATemporaryFailureOfURSSAFIsRecoverableWithItsDelay(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->gateway->willVerifyEmployer(UrssafUnavailable::withStatus(Operation::VerifyEmployer, 503, 120, ApiErrors::none()));

        try {
            self::tenantHandler($harness, new OperationLocks(null))(new SyncEmployerStatusesMessage(Harness::TENANT));
            static::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $failure) {
            RetryDelay::assertCarries(120_000, $failure);
        }
    }

    public function testADeletedAccountAndAnInvalidIdentifierAreUnrecoverable(): void
    {
        $harness = new Harness();
        $deleted = static fn() => self::accountHandler($harness, new OperationLocks(null))(new SyncEmployerStatusMessage(Harness::TENANT, 'account-9'));
        $invalid = static fn() => self::tenantHandler($harness, new OperationLocks(null))(new SyncEmployerStatusesMessage(' '));

        foreach ([$deleted, $invalid] as $handle) {
            try {
                $handle();
                static::fail('Expected an unrecoverable exception.');
            } catch (UnrecoverableMessageHandlingException $failure) {
                static::assertInstanceOf(UrssafException::class, $failure->getPrevious());
            }
        }
    }

    private static function tenantHandler(Harness $harness, OperationLocks $locks): SyncEmployerStatusesMessageHandler
    {
        return new SyncEmployerStatusesMessageHandler(
            new SyncEmployerStatusesHandler($harness->accounts, self::refresher($harness), $harness->policy),
            $locks,
        );
    }

    private static function accountHandler(Harness $harness, OperationLocks $locks): SyncEmployerStatusMessageHandler
    {
        return new SyncEmployerStatusMessageHandler(
            new SyncEmployerStatusHandler(new EmployerLocator($harness->accounts), self::refresher($harness), $harness->mandates, $harness->accounts),
            $locks,
        );
    }

    private static function refresher(Harness $harness): EmployerStatusRefresher
    {
        return new EmployerStatusRefresher($harness->gateway, $harness->mandates, $harness->accounts, $harness->clock, $harness->unitOfWork);
    }
}
