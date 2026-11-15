<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\RegistrationOrigin;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

final class EventsTest extends TestCase
{
    public function testEventsExposeTheirConstructorArguments(): void
    {
        $cesu = CesuNumber::fromString('Z0003752390009');
        $now = EmployerSamples::instant();
        $status = EmployerSamples::status(true);

        $registered = new EmployerRegistered(EmployerSamples::accountId(), EmployerSamples::tenantId(), EmployerSamples::externalReference(), $cesu, RegistrationOrigin::Imported, $now);
        $changed = new EmployerStageChanged(EmployerSamples::accountId(), EmployerSamples::tenantId(), EmployerSamples::externalReference(), $cesu, EmployerStage::Registered, EmployerStage::AiciActive, $now);
        $refreshed = new EmployerStatusRefreshed(EmployerSamples::accountId(), EmployerSamples::tenantId(), EmployerSamples::externalReference(), $cesu, EmployerStage::MandateNotified, EmployerStage::MandateConfirmed, $status, $now);

        static::assertSame(RegistrationOrigin::Imported, $registered->origin());
        static::assertSame(EmployerStage::Registered, $changed->from());
        static::assertSame(EmployerStage::AiciActive, $changed->to());
        static::assertSame(EmployerStage::MandateNotified, $refreshed->previousStage());
        static::assertSame(EmployerStage::MandateConfirmed, $refreshed->stage());
        static::assertSame($status, $refreshed->status());
        foreach ([$registered, $changed, $refreshed] as $event) {
            static::assertSame($cesu, $event->cesuNumber());
            static::assertSame('2026-11-15T10:00:00+00:00', $event->occurredAt()->format('c'));
            static::assertSame('account-1', $event->accountId()->value());
        }
    }
}
