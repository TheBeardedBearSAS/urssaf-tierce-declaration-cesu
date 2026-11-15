<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Mandate\Event;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateNotified;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Mandate\MandateSamples;

final class EventsTest extends TestCase
{
    public function testEventsExposeTheirConstructorArguments(): void
    {
        $cesu = CesuNumber::fromString('Z0003752390009');
        $now = MandateSamples::notifiedAt();

        foreach ([
            new MandateNotified(MandateSamples::id(), EmployerSamples::tenantId(), EmployerSamples::externalReference(), $cesu, $now),
            new MandateConfirmed(MandateSamples::id(), EmployerSamples::tenantId(), EmployerSamples::externalReference(), $cesu, $now),
            new MandateCancelled(MandateSamples::id(), EmployerSamples::tenantId(), EmployerSamples::externalReference(), $cesu, $now),
        ] as $event) {
            static::assertSame('mandate-1', $event->mandateId()->value());
            static::assertSame('tenant-1', $event->tenantId()->value());
            static::assertSame('employer-ref-1', $event->externalReference()->value());
            static::assertSame($cesu, $event->cesuNumber());
            static::assertSame('2026-11-15T10:00:00+00:00', $event->occurredAt()->format('c'));
        }
    }
}
