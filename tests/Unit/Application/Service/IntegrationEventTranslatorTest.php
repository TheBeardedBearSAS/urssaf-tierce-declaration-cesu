<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\IntegrationEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerStatusChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateNotified;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\RegistrationOrigin;

final class IntegrationEventTranslatorTest extends TestCase
{
    public function testEveryDomainEventOfTheCatalogueIsTranslatedInOrder(): void
    {
        $translated = IntegrationEventTranslator::translate(
            IntegrationEventSamples::employerRegistered(),
            IntegrationEventSamples::employerStatusRefreshed(),
            IntegrationEventSamples::employerStageChanged(),
            IntegrationEventSamples::employerForgotten(),
            IntegrationEventSamples::mandateNotified(),
            IntegrationEventSamples::mandateConfirmed(),
            IntegrationEventSamples::mandateCancelled(),
            IntegrationEventSamples::declarationDeclared(),
            IntegrationEventSamples::declarationRejected('ERR_A'),
            IntegrationEventSamples::declarationOutcomeUnknown(),
            IntegrationEventSamples::declarationAlreadyDeclared(),
        );

        static::assertSame(
            [
                EmployerRegistered::class,
                EmployerStatusChanged::class,
                EmployerStatusChanged::class,
                EmployerForgotten::class,
                MandateNotified::class,
                MandateConfirmed::class,
                MandateCancelled::class,
                DeclarationDeclared::class,
                DeclarationRejected::class,
                DeclarationOutcomeUnknown::class,
                DeclarationAlreadyDeclared::class,
            ],
            array_map(static fn(IntegrationEvent $event): string => $event::class, $translated),
        );
    }

    public function testTheOtherDomainEventsHaveNoIntegrationEvent(): void
    {
        $translated = IntegrationEventTranslator::translate(
            IntegrationEventSamples::declarationRecorded(),
            IntegrationEventSamples::mandateNotified(),
            IntegrationEventSamples::declarationSubmissionFailed(),
            IntegrationEventSamples::declarationCredentialsRejected(),
            IntegrationEventSamples::declarationRequeued(),
        );

        static::assertCount(1, $translated);
        static::assertInstanceOf(MandateNotified::class, $translated[0]);
        static::assertSame([], IntegrationEventTranslator::translate());
    }

    public function testTheEventIdsLookLikeUuidsAndAreDistinct(): void
    {
        $ids = array_map(static fn(IntegrationEvent $event): string => $event->eventId, IntegrationEventTranslator::translate(
            IntegrationEventSamples::employerRegistered(),
            IntegrationEventSamples::employerRegistered(),
            IntegrationEventSamples::employerRegistered(RegistrationOrigin::Registered, 'account-2'),
            IntegrationEventSamples::mandateNotified(),
        ));

        static::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ids[0]);
        static::assertSame($ids[0], $ids[1]);
        static::assertCount(3, array_unique($ids));
    }

    public function testTheEventIdsAreStableAcrossVersions(): void
    {
        // The hosts deduplicate on these ids: changing how they are derived is a breaking change.
        $ids = array_map(static fn(IntegrationEvent $event): string => $event->eventId, IntegrationEventTranslator::translate(
            IntegrationEventSamples::employerRegistered(),
            IntegrationEventSamples::employerStatusRefreshed(),
            IntegrationEventSamples::employerStageChanged(),
            IntegrationEventSamples::mandateNotified(),
            IntegrationEventSamples::mandateConfirmed(),
            IntegrationEventSamples::mandateCancelled(),
            IntegrationEventSamples::declarationDeclared(),
            IntegrationEventSamples::declarationRejected('ERR_A'),
            IntegrationEventSamples::declarationOutcomeUnknown(),
            IntegrationEventSamples::declarationAlreadyDeclared(),
        ));

        static::assertSame([
            'ffb8ba85-68ac-2277-9317-dd4e44d41927',
            '34a7bb41-d083-ff8b-30da-1a0caf03fe56',
            '517e05d3-bf74-38ed-390e-03cd9ab205a9',
            '44296186-25fe-247b-089c-1ba519edef5d',
            'e38a38a9-e0d6-5f97-e50a-cba2e50a84f0',
            'b2249c3d-a160-9240-ec3e-9e25dbe8a9ab',
            '830d82cd-228b-91a1-008a-c6f0f7777590',
            'd6976276-0aa1-d8f1-6a07-a63915dbc93b',
            '31abc1c4-72b9-d81d-5d11-490cddc77c33',
            'f71075c8-eebf-2181-be65-b9a8a1f8ff29',
        ], $ids);
    }
}
