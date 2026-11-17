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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\RegistrationOrigin;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
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

    public function testTheForgottenEventExposesItsConstructorArguments(): void
    {
        $cesu = CesuNumber::fromString('Z0003752390009');
        $mandateIds = [MandateId::fromString('mandate-1')];
        $recordIds = [DeclarationRecordId::fromString('record-1'), DeclarationRecordId::fromString('record-2')];

        $forgotten = new EmployerForgotten(EmployerSamples::accountId(), EmployerSamples::tenantId(), EmployerSamples::externalReference(), $cesu, $mandateIds, $recordIds, EmployerSamples::instant());

        static::assertSame('account-1', $forgotten->accountId()->value());
        static::assertSame('tenant-1', $forgotten->tenantId()->value());
        static::assertSame(EmployerSamples::externalReference()->value(), $forgotten->externalReference()->value());
        static::assertSame($cesu, $forgotten->cesuNumber());
        static::assertSame($mandateIds, $forgotten->mandateIds());
        static::assertSame($recordIds, $forgotten->declarationRecordIds());
        static::assertSame('2026-11-15T10:00:00+00:00', $forgotten->occurredAt()->format('c'));
    }
}
