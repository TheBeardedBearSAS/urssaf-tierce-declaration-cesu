<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Mandate;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateNotified;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class MandateTest extends TestCase
{
    public function testNotify(): void
    {
        $mandate = Mandate::notify(
            MandateSamples::id(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            MandateSamples::notifiedAt(),
        );

        static::assertSame('mandate-1', $mandate->id()->value());
        static::assertSame('tenant-1', $mandate->tenantId()->value());
        static::assertSame('employer-ref-1', $mandate->externalReference()->value());
        static::assertTrue($mandate->employer()->equals(EmployerSamples::employer()));
        static::assertSame('Z0003752390009', $mandate->cesuNumber()->value());
        static::assertSame(MandateState::Notified, $mandate->state());
        static::assertTrue($mandate->isActive());
        static::assertSame('2026-11-15T10:00:00Z', $mandate->notifiedAt()->toIso8601());
        static::assertNull($mandate->confirmedAt());
        static::assertNull($mandate->cancelledAt());
        static::assertNull($mandate->lastCheck());
        static::assertSame('2026-11-15T10:00:00Z', $mandate->updatedAt()->toIso8601());
        static::assertSame('2026-11-15T10:00:00Z', $mandate->timeline()->notifiedAt()->toIso8601());

        $events = $mandate->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(MandateNotified::class, $event);
        static::assertSame('mandate-1', $event->mandateId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame('Z0003752390009', $event->cesuNumber()->value());
        static::assertSame('2026-11-15T10:00:00+00:00', $event->occurredAt()->format('c'));
        static::assertSame([], $mandate->releaseEvents());
    }

    public function testStatusPresentConfirmsANotifiedMandate(): void
    {
        $mandate = MandateSamples::notified();
        $now = MandateSamples::later();

        $mandate->applyStatus(true, $now);

        static::assertSame(MandateState::Confirmed, $mandate->state());
        static::assertSame($now, $mandate->confirmedAt());
        static::assertNull($mandate->cancelledAt());
        static::assertSame($now, $mandate->updatedAt());
        static::assertSame('2026-11-15T10:00:00Z', $mandate->notifiedAt()->toIso8601());
        $check = $mandate->lastCheck();
        static::assertNotNull($check);
        static::assertTrue($check->mandatePresent());
        static::assertSame($now, $check->checkedAt());
        $events = $mandate->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(MandateConfirmed::class, $event);
        static::assertSame('mandate-1', $event->mandateId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('employer-ref-1', $event->externalReference()->value());
        static::assertSame('Z0003752390009', $event->cesuNumber()->value());
        static::assertSame('2026-11-16T11:30:00+00:00', $event->occurredAt()->format('c'));
    }

    public function testStatusPresentRefreshesAConfirmedMandateWithoutEvent(): void
    {
        $mandate = MandateSamples::inState(MandateState::Confirmed);
        $confirmedAt = $mandate->confirmedAt();
        $now = MandateSamples::later();

        $mandate->applyStatus(true, $now);

        static::assertSame(MandateState::Confirmed, $mandate->state());
        static::assertSame($confirmedAt, $mandate->confirmedAt());
        static::assertSame($now, $mandate->updatedAt());
        static::assertSame($now, $mandate->lastCheck()?->checkedAt());
        static::assertSame([], $mandate->releaseEvents());
    }

    public function testStatusAbsentNeverChangesTheState(): void
    {
        foreach (MandateState::cases() as $state) {
            $mandate = MandateSamples::inState($state);
            $now = MandateSamples::later();

            $mandate->applyStatus(false, $now);

            static::assertSame($state, $mandate->state(), $state->value);
            $check = $mandate->lastCheck();
            static::assertNotNull($check, $state->value);
            static::assertFalse($check->mandatePresent(), $state->value);
            static::assertSame($now, $check->checkedAt(), $state->value);
            static::assertSame($now, $mandate->updatedAt(), $state->value);
            static::assertSame([], $mandate->releaseEvents(), $state->value);
        }
    }

    public function testStatusAbsentKeepsTheConfirmationInstant(): void
    {
        $mandate = MandateSamples::inState(MandateState::Confirmed);
        $confirmedAt = $mandate->confirmedAt();

        $mandate->applyStatus(false, MandateSamples::later());

        static::assertSame($confirmedAt, $mandate->confirmedAt());
    }

    public function testStatusPresentIsRefusedForACancelledMandate(): void
    {
        $mandate = MandateSamples::inState(MandateState::Cancelled);
        $updatedAt = $mandate->updatedAt();

        try {
            $mandate->applyStatus(true, MandateSamples::later());
            static::fail('Expected an InvalidStateTransition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('mandate.invalid_state_transition', $exception->violationCode());
            static::assertSame('cancelled', $exception->from());
            static::assertSame('confirmed', $exception->to());
        }

        static::assertSame(MandateState::Cancelled, $mandate->state());
        static::assertNull($mandate->lastCheck());
        static::assertSame($updatedAt, $mandate->updatedAt());
        static::assertSame([], $mandate->releaseEvents());
    }

    public function testCancel(): void
    {
        foreach ([MandateState::Notified, MandateState::Confirmed] as $state) {
            $mandate = MandateSamples::inState($state);
            $confirmedAt = $mandate->confirmedAt();
            $now = MandateSamples::later();

            $mandate->cancel($now);

            static::assertSame(MandateState::Cancelled, $mandate->state(), $state->value);
            static::assertFalse($mandate->isActive(), $state->value);
            static::assertSame($now, $mandate->cancelledAt(), $state->value);
            static::assertSame($now, $mandate->updatedAt(), $state->value);
            static::assertSame($confirmedAt, $mandate->confirmedAt(), $state->value);
            $events = $mandate->releaseEvents();
            static::assertCount(1, $events, $state->value);
            $event = $events[0];
            static::assertInstanceOf(MandateCancelled::class, $event);
            static::assertSame('mandate-1', $event->mandateId()->value());
            static::assertSame('tenant-1', $event->tenantId()->value());
            static::assertSame('employer-ref-1', $event->externalReference()->value());
            static::assertSame('Z0003752390009', $event->cesuNumber()->value());
            static::assertSame('2026-11-16T11:30:00+00:00', $event->occurredAt()->format('c'));
        }
    }

    public function testCancelIsRefusedWhenAlreadyCancelled(): void
    {
        $mandate = MandateSamples::inState(MandateState::Cancelled);
        $cancelledAt = $mandate->cancelledAt();

        try {
            $mandate->cancel(MandateSamples::later());
            static::fail('Expected an InvalidStateTransition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('cancelled', $exception->from());
            static::assertSame('cancelled', $exception->to());
        }

        static::assertSame($cancelledAt, $mandate->cancelledAt());
        static::assertSame([], $mandate->releaseEvents());
    }

    public function testEventsAreReleasedInOrder(): void
    {
        $mandate = Mandate::notify(
            MandateSamples::id(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            MandateSamples::notifiedAt(),
        );
        $mandate->applyStatus(true, Instant::fromIso8601('2026-11-16T10:00:00Z'));
        $mandate->cancel(Instant::fromIso8601('2026-11-17T10:00:00Z'));

        static::assertSame(
            [MandateNotified::class, MandateConfirmed::class, MandateCancelled::class],
            array_map(static fn(DomainEvent $event): string => $event::class, $mandate->releaseEvents()),
        );
    }
}
