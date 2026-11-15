<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * Mandates in every state, events released.
 */
final class MandateSamples
{
    public static function id(): MandateId
    {
        return MandateId::fromString('mandate-1');
    }

    public static function notifiedAt(): Instant
    {
        return Instant::fromIso8601('2026-11-15T10:00:00Z');
    }

    public static function later(): Instant
    {
        return Instant::fromIso8601('2026-11-16T11:30:00Z');
    }

    public static function notified(): Mandate
    {
        $mandate = Mandate::notify(
            self::id(),
            EmployerSamples::tenantId(),
            EmployerSamples::externalReference(),
            EmployerSamples::employer(),
            self::notifiedAt(),
        );
        $mandate->releaseEvents();

        return $mandate;
    }

    public static function inState(MandateState $state): Mandate
    {
        $mandate = self::notified();
        if ($state === MandateState::Confirmed) {
            $mandate->applyStatus(true, Instant::fromIso8601('2026-11-15T12:00:00Z'));
        }
        if ($state === MandateState::Cancelled) {
            $mandate->cancel(Instant::fromIso8601('2026-11-15T12:00:00Z'));
        }
        $mandate->releaseEvents();

        return $mandate;
    }
}
