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

use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationAlreadyDeclared as DomainDeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationDeclared as DomainDeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationOutcomeUnknown as DomainDeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRejected as DomainDeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;

/**
 * Integration events of the declaration workflow (ADR 0012). Requeued declarations, failed submissions and rejected
 * credentials have none: their domain events carry no external reference.
 */
final class DeclarationEventTranslator
{
    /**
     * @return list<IntegrationEvent> the integration event of the domain event, none when it has none
     */
    public static function translate(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof DomainDeclarationDeclared => [self::declared($event)],
            $event instanceof DomainDeclarationRejected => [self::rejected($event)],
            $event instanceof DomainDeclarationOutcomeUnknown => [self::outcomeUnknown($event)],
            $event instanceof DomainDeclarationAlreadyDeclared => [self::alreadyDeclared($event)],
            default => [],
        };
    }

    private static function declared(DomainDeclarationDeclared $event): DeclarationDeclared
    {
        $aggregateId = $event->recordId()->value();
        $declarationId = $event->declarationId()->value();

        return new DeclarationDeclared(
            IntegrationEventFacts::id(DeclarationDeclared::TYPE, $aggregateId, $event->occurredAt(), [$declarationId]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $declarationId,
        );
    }

    private static function rejected(DomainDeclarationRejected $event): DeclarationRejected
    {
        $aggregateId = $event->recordId()->value();
        $codes = array_map(static fn(SubmissionError $error): string => $error->code(), $event->errors());

        return new DeclarationRejected(
            IntegrationEventFacts::id(DeclarationRejected::TYPE, $aggregateId, $event->occurredAt(), $codes),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $codes,
        );
    }

    private static function outcomeUnknown(DomainDeclarationOutcomeUnknown $event): DeclarationOutcomeUnknown
    {
        $aggregateId = $event->recordId()->value();
        $reason = $event->reason()->value;

        return new DeclarationOutcomeUnknown(
            IntegrationEventFacts::id(DeclarationOutcomeUnknown::TYPE, $aggregateId, $event->occurredAt(), [$reason]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $reason,
        );
    }

    private static function alreadyDeclared(DomainDeclarationAlreadyDeclared $event): DeclarationAlreadyDeclared
    {
        $aggregateId = $event->recordId()->value();

        return new DeclarationAlreadyDeclared(
            IntegrationEventFacts::id(DeclarationAlreadyDeclared::TYPE, $aggregateId, $event->occurredAt(), []),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
        );
    }
}
