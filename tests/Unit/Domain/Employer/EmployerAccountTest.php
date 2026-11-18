<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\RegistrationOrigin;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;

/**
 * One method per command and scenario of the aggregate.
 *
 * @mago-expect lint:too-many-methods
 */
final class EmployerAccountTest extends TestCase
{
    private static function register(): EmployerAccount
    {
        return EmployerAccount::register(
            EmployerSamples::accountId(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            EmployerSamples::instant(),
        );
    }

    public function testRegister(): void
    {
        $account = self::register();

        static::assertSame('account-1', $account->id()->value());
        static::assertSame('tenant-1', $account->tenantId()->value());
        static::assertSame('employer-ref-1', $account->externalReference()->value());
        static::assertTrue($account->employer()->equals(EmployerSamples::employer()));
        static::assertSame('Z0003752390009', $account->cesuNumber()->value());
        static::assertSame('1975-01-30', $account->birthDate()->toString());
        static::assertSame(RegistrationOrigin::Registered, $account->origin());
        static::assertSame('2026-11-15T10:00:00Z', $account->createdAt()->toIso8601());
        static::assertSame(EmployerStage::Registered, $account->stage());
        static::assertNull($account->lastStatus());
        static::assertNull($account->lastStatusEmployeeKind());
        static::assertNull($account->lastStatusEmployeeCesuNumber());
        static::assertNull($account->lastCheckedAt());
        static::assertSame('2026-11-15T10:00:00Z', $account->updatedAt()->toIso8601());
        static::assertSame(EmployerStage::Registered, $account->tracking()->stage());

        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(EmployerRegistered::class, $event);
        static::assertSame('account-1', $event->accountId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame('Z0003752390009', $event->cesuNumber()->value());
        static::assertSame(RegistrationOrigin::Registered, $event->origin());
        static::assertSame('2026-11-15T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame([], $account->releaseEvents());
    }

    public function testImport(): void
    {
        $account = EmployerAccount::import(
            EmployerSamples::accountId(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            EmployerSamples::instant(),
        );

        static::assertSame(RegistrationOrigin::Imported, $account->origin());
        static::assertSame(EmployerStage::Registered, $account->stage());
        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(EmployerRegistered::class, $events[0]);
        static::assertSame(RegistrationOrigin::Imported, $events[0]->origin());
    }

    public function testMandateNotified(): void
    {
        $account = EmployerSamples::account();
        $now = EmployerSamples::instant('2026-11-16T10:00:00Z');

        $account->markMandateNotified($now);

        static::assertSame(EmployerStage::MandateNotified, $account->stage());
        static::assertSame($now, $account->updatedAt());
        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(EmployerStageChanged::class, $event);
        static::assertSame('account-1', $event->accountId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame('Z0003752390009', $event->cesuNumber()->value());
        static::assertSame(EmployerStage::Registered, $event->from());
        static::assertSame(EmployerStage::MandateNotified, $event->to());
        static::assertSame('2026-11-16T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame([], $account->releaseEvents());
    }

    public function testRepeatedMandateNotificationOnlyTouchesTheAccount(): void
    {
        $account = EmployerSamples::account();
        $account->markMandateNotified(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();
        $later = EmployerSamples::instant('2026-11-17T10:00:00Z');

        $account->markMandateNotified($later);

        static::assertSame(EmployerStage::MandateNotified, $account->stage());
        static::assertSame($later, $account->updatedAt());
        static::assertSame([], $account->releaseEvents());
    }

    public function testMandateCancelled(): void
    {
        $account = EmployerSamples::account();
        $now = EmployerSamples::instant('2026-11-16T10:00:00Z');

        $account->markMandateCancelled($now);

        static::assertSame(EmployerStage::MandateCancelled, $account->stage());
        static::assertSame($now, $account->updatedAt());
        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(EmployerStageChanged::class, $events[0]);
        static::assertSame(EmployerStage::Registered, $events[0]->from());
        static::assertSame(EmployerStage::MandateCancelled, $events[0]->to());

        $account->markMandateCancelled(EmployerSamples::instant('2026-11-17T10:00:00Z'));
        static::assertSame([], $account->releaseEvents());
    }

    public function testNotificationAfterCancellation(): void
    {
        $account = EmployerSamples::account();
        $account->markMandateCancelled(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();

        $account->markMandateNotified(EmployerSamples::instant('2026-11-17T10:00:00Z'));

        static::assertSame(EmployerStage::MandateNotified, $account->stage());
        static::assertCount(1, $account->releaseEvents());
    }

    public function testFirstStatusRecordsAnEvent(): void
    {
        $account = EmployerSamples::account();
        $status = EmployerSamples::status(true);
        $employee = EmployeeSamples::byCesuNumber();
        $now = EmployerSamples::instant('2026-11-16T10:00:00Z');

        $account->applyStatus($status, $employee, $now);

        static::assertSame(EmployerStage::MandateConfirmed, $account->stage());
        static::assertSame($status, $account->lastStatus());
        static::assertSame(EmployeeReferenceKind::CesuNumber, $account->lastStatusEmployeeKind());
        static::assertSame($employee->cesuNumber(), $account->lastStatusEmployeeCesuNumber());
        static::assertSame($now, $account->lastCheckedAt());
        static::assertSame($now, $account->updatedAt());
        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(EmployerStatusRefreshed::class, $event);
        static::assertSame('account-1', $event->accountId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame('Z0003752390009', $event->cesuNumber()->value());
        static::assertSame(EmployerStage::Registered, $event->previousStage());
        static::assertSame(EmployerStage::MandateConfirmed, $event->stage());
        static::assertSame($status, $event->status());
        static::assertSame('2026-11-16T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame([], $account->releaseEvents());
    }

    public function testFirstStatusWithoutChangeStillRecordsAnEvent(): void
    {
        $account = EmployerSamples::account();

        $account->applyStatus(EmployerSamples::status(false), null, EmployerSamples::instant('2026-11-16T10:00:00Z'));

        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(EmployerStatusRefreshed::class, $events[0]);
        static::assertSame(EmployerStage::Registered, $events[0]->previousStage());
        static::assertSame(EmployerStage::Registered, $events[0]->stage());
        static::assertNull($account->lastStatusEmployeeKind());
        static::assertNull($account->lastStatusEmployeeCesuNumber());
    }

    public function testAnUnchangedStatusOnlyRefreshesTheCheckInstants(): void
    {
        $account = EmployerSamples::account();
        $account->applyStatus(EmployerSamples::status(true), null, EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();
        $later = EmployerSamples::instant('2026-11-17T10:00:00Z');

        $account->applyStatus(EmployerSamples::status(true), null, $later);

        static::assertSame([], $account->releaseEvents());
        static::assertSame($later, $account->lastCheckedAt());
        static::assertSame($later, $account->updatedAt());
    }

    public function testADifferentStatusRecordsAnEvent(): void
    {
        $account = EmployerSamples::account();
        $account->applyStatus(EmployerSamples::status(true), null, EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();

        $account->applyStatus(EmployerSamples::statusWithCesuPlus(true, 'active'), null, EmployerSamples::instant('2026-11-17T10:00:00Z'));

        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(EmployerStatusRefreshed::class, $events[0]);
        static::assertSame(EmployerStage::MandateConfirmed, $events[0]->previousStage());
        static::assertSame(EmployerStage::MandateConfirmed, $events[0]->stage());
    }

    public function testADifferentEmployeeRecordsAnEvent(): void
    {
        $account = EmployerSamples::account();
        $account->applyStatus(EmployerSamples::status(true), null, EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();

        $account->applyStatus(EmployerSamples::status(true), EmployeeSamples::byCesuNumber(), EmployerSamples::instant('2026-11-17T10:00:00Z'));
        static::assertCount(1, $account->releaseEvents());

        $account->applyStatus(EmployerSamples::status(true), EmployeeSamples::byCesuNumber(), EmployerSamples::instant('2026-11-18T10:00:00Z'));
        static::assertSame([], $account->releaseEvents());

        $account->applyStatus(EmployerSamples::status(true), EmployeeSamples::byNir(), EmployerSamples::instant('2026-11-19T10:00:00Z'));
        static::assertCount(1, $account->releaseEvents());
    }

    public function testNoNirNorAdministrativeDataIsKept(): void
    {
        $account = EmployerSamples::account();
        $now = EmployerSamples::instant('2026-11-16T10:00:00Z');

        $account->applyStatus(EmployerSamples::status(true), EmployeeSamples::byNir(), $now);

        static::assertSame(EmployeeReferenceKind::Nir, $account->lastStatusEmployeeKind());
        static::assertNull($account->lastStatusEmployeeCesuNumber());

        $account->applyStatus(EmployerSamples::status(true), EmployeeSamples::byIdentity(), $now);

        static::assertSame(EmployeeReferenceKind::Identity, $account->lastStatusEmployeeKind());
        static::assertNull($account->lastStatusEmployeeCesuNumber());
    }

    public function testAStageChangeWithTheSameSnapshotRecordsAnEvent(): void
    {
        $account = EmployerSamples::account();
        $account->applyStatus(EmployerSamples::status(true), null, EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->markMandateCancelled(EmployerSamples::instant('2026-11-16T11:00:00Z'));
        $account->releaseEvents();

        $account->applyStatus(EmployerSamples::status(true), null, EmployerSamples::instant('2026-11-17T10:00:00Z'));

        static::assertSame(EmployerStage::MandateConfirmed, $account->stage());
        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(EmployerStatusRefreshed::class, $events[0]);
        static::assertSame(EmployerStage::MandateCancelled, $events[0]->previousStage());
        static::assertSame(EmployerStage::MandateConfirmed, $events[0]->stage());
    }

    public function testMandateAbsentFromAConfirmedEmployerGoesBackToRegistered(): void
    {
        $account = EmployerSamples::account();
        $account->applyStatus(EmployerSamples::status(true), null, EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();

        $account->applyStatus(EmployerSamples::status(false), null, EmployerSamples::instant('2026-11-17T10:00:00Z'));

        static::assertSame(EmployerStage::Registered, $account->stage());
        static::assertCount(1, $account->releaseEvents());
    }

    public function testEventsAreReleasedInOrder(): void
    {
        $account = self::register();
        $account->markMandateNotified(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->applyStatus(EmployerSamples::status(true, true), null, EmployerSamples::instant('2026-11-17T10:00:00Z'));

        $events = $account->releaseEvents();

        static::assertSame(
            [EmployerRegistered::class, EmployerStageChanged::class, EmployerStatusRefreshed::class],
            array_map(static fn(DomainEvent $event): string => $event::class, $events),
        );
        static::assertSame(EmployerStage::AiciActive, $account->stage());
    }

    public function testForgettingRecordsOneEventWithTheErasedIdsAndKeepsTheState(): void
    {
        $account = EmployerSamples::account();
        $account->markMandateNotified(EmployerSamples::instant('2026-11-16T10:00:00Z'));
        $account->releaseEvents();
        $updatedAt = $account->updatedAt();
        $mandateIds = [MandateId::fromString('mandate-1')];
        $recordIds = [DeclarationRecordId::fromString('record-1'), DeclarationRecordId::fromString('record-2')];

        $account->forget(EmployerSamples::instant('2026-11-18T10:00:00Z'), $mandateIds, $recordIds);

        $events = $account->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(EmployerForgotten::class, $event);
        static::assertSame($account->id(), $event->accountId());
        static::assertSame($account->tenantId(), $event->tenantId());
        static::assertSame($account->externalReference(), $event->externalReference());
        static::assertSame($account->cesuNumber()->value(), $event->cesuNumber()->value());
        static::assertSame($mandateIds, $event->mandateIds());
        static::assertSame($recordIds, $event->declarationRecordIds());
        static::assertSame('2026-11-18T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame(EmployerStage::MandateNotified, $account->stage());
        static::assertSame($updatedAt, $account->updatedAt());
        static::assertSame([], $account->releaseEvents());
    }
}
