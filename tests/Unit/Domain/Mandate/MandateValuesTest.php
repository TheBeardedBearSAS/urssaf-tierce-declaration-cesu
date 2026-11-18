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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandatePresenceCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateTimeline;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

final class MandateValuesTest extends TestCase
{
    public function testPresenceCheck(): void
    {
        $at = Instant::fromIso8601('2026-11-15T10:00:00Z');
        $check = MandatePresenceCheck::of(true, $at);

        static::assertTrue($check->mandatePresent());
        static::assertFalse(MandatePresenceCheck::of(false, $at)->mandatePresent());
        static::assertSame($at, $check->checkedAt());
        static::assertTrue($check->equals(MandatePresenceCheck::of(true, Instant::fromIso8601('2026-11-15T10:00:00Z'))));
        static::assertFalse($check->equals(MandatePresenceCheck::of(false, $at)));
        static::assertFalse($check->equals(MandatePresenceCheck::of(true, Instant::fromIso8601('2026-11-15T10:00:01Z'))));
    }

    public function testTimeline(): void
    {
        $notified = Instant::fromIso8601('2026-11-15T10:00:00Z');
        $confirmed = Instant::fromIso8601('2026-11-16T10:00:00Z');
        $cancelled = Instant::fromIso8601('2026-11-17T10:00:00Z');
        $touched = Instant::fromIso8601('2026-11-18T10:00:00Z');

        $timeline = MandateTimeline::notified($notified);
        static::assertSame($notified, $timeline->notifiedAt());
        static::assertNull($timeline->confirmedAt());
        static::assertNull($timeline->cancelledAt());
        static::assertSame($notified, $timeline->updatedAt());

        $withConfirmation = $timeline->withConfirmation($confirmed);
        static::assertSame($notified, $withConfirmation->notifiedAt());
        static::assertSame($confirmed, $withConfirmation->confirmedAt());
        static::assertNull($withConfirmation->cancelledAt());
        static::assertSame($confirmed, $withConfirmation->updatedAt());

        $withCancellation = $withConfirmation->withCancellation($cancelled);
        static::assertSame($notified, $withCancellation->notifiedAt());
        static::assertSame($confirmed, $withCancellation->confirmedAt());
        static::assertSame($cancelled, $withCancellation->cancelledAt());
        static::assertSame($cancelled, $withCancellation->updatedAt());

        $touchedTimeline = $withCancellation->touched($touched);
        static::assertSame($notified, $touchedTimeline->notifiedAt());
        static::assertSame($confirmed, $touchedTimeline->confirmedAt());
        static::assertSame($cancelled, $touchedTimeline->cancelledAt());
        static::assertSame($touched, $touchedTimeline->updatedAt());
    }

    public function testTimelineEquality(): void
    {
        $a = Instant::fromIso8601('2026-11-15T10:00:00Z');
        $b = Instant::fromIso8601('2026-11-16T10:00:00Z');
        $full = MandateTimeline::notified($a)->withConfirmation($a)->withCancellation($a);

        static::assertTrue(MandateTimeline::notified($a)->equals(MandateTimeline::notified(Instant::fromIso8601('2026-11-15T10:00:00Z'))));
        static::assertFalse(MandateTimeline::notified($a)->equals(MandateTimeline::notified($b)));
        static::assertFalse(MandateTimeline::notified($a)->equals(MandateTimeline::notified($a)->withConfirmation($a)));
        static::assertFalse(MandateTimeline::notified($a)->withConfirmation($a)->equals(MandateTimeline::notified($a)));
        static::assertFalse(MandateTimeline::notified($a)->withConfirmation($a)->equals(MandateTimeline::notified($a)->withConfirmation($b)->touched($a)));
        static::assertFalse(MandateTimeline::notified($a)->withCancellation($a)->equals(MandateTimeline::notified($a)));
        static::assertFalse(MandateTimeline::notified($a)->equals(MandateTimeline::notified($a)->withCancellation($a)));
        static::assertFalse(MandateTimeline::notified($a)->withCancellation($a)->equals(MandateTimeline::notified($a)->withCancellation($b)->touched($a)));
        static::assertFalse(MandateTimeline::notified($a)->equals(MandateTimeline::notified($a)->touched($b)));
        static::assertTrue($full->equals(MandateTimeline::notified($a)->withConfirmation($a)->withCancellation($a)));
        static::assertFalse($full->equals(MandateTimeline::notified($b)->withConfirmation($a)->withCancellation($a)->touched($a)));
    }
}
