<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateNotified;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateCancelled as DomainMandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateConfirmed as DomainMandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateNotified as DomainMandateNotified;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;

/**
 * Integration events of the mandate workflow (ADR 0012).
 */
final class MandateEventTranslator
{
    /**
     * @return list<IntegrationEvent> the integration event of the domain event, none when it has none
     */
    public static function translate(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof DomainMandateNotified => [self::notified($event)],
            $event instanceof DomainMandateConfirmed => [self::confirmed($event)],
            $event instanceof DomainMandateCancelled => [self::cancelled($event)],
            default => [],
        };
    }

    private static function notified(DomainMandateNotified $event): MandateNotified
    {
        $aggregateId = $event->mandateId()->value();
        $cesuNumber = $event->cesuNumber()->value();

        return new MandateNotified(
            IntegrationEventFacts::id(MandateNotified::TYPE, $aggregateId, $event->occurredAt(), [$cesuNumber]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $cesuNumber,
        );
    }

    private static function confirmed(DomainMandateConfirmed $event): MandateConfirmed
    {
        $aggregateId = $event->mandateId()->value();
        $cesuNumber = $event->cesuNumber()->value();

        return new MandateConfirmed(
            IntegrationEventFacts::id(MandateConfirmed::TYPE, $aggregateId, $event->occurredAt(), [$cesuNumber]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $cesuNumber,
        );
    }

    private static function cancelled(DomainMandateCancelled $event): MandateCancelled
    {
        $aggregateId = $event->mandateId()->value();
        $cesuNumber = $event->cesuNumber()->value();

        return new MandateCancelled(
            IntegrationEventFacts::id(MandateCancelled::TYPE, $aggregateId, $event->occurredAt(), [$cesuNumber]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $cesuNumber,
        );
    }
}
