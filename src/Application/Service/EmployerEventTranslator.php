<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerStatusChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerForgotten as DomainEmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerRegistered as DomainEmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;

/**
 * Integration events of the employer workflow (ADR 0012).
 */
final class EmployerEventTranslator
{
    /**
     * @return list<IntegrationEvent> the integration event of the domain event, none when it has none
     */
    public static function translate(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof DomainEmployerRegistered => [self::registered($event)],
            $event instanceof EmployerStatusRefreshed => [self::statusRefreshed($event)],
            $event instanceof EmployerStageChanged => [self::stageChanged($event)],
            $event instanceof DomainEmployerForgotten => [self::forgotten($event)],
            default => [],
        };
    }

    private static function forgotten(DomainEmployerForgotten $event): EmployerForgotten
    {
        $aggregateId = $event->accountId()->value();
        $cesuNumber = $event->cesuNumber()->value();

        return new EmployerForgotten(
            IntegrationEventFacts::id(EmployerForgotten::TYPE, $aggregateId, $event->occurredAt(), [$cesuNumber]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $cesuNumber,
            array_map(static fn(MandateId $id): string => $id->value(), $event->mandateIds()),
            array_map(static fn(DeclarationRecordId $id): string => $id->value(), $event->declarationRecordIds()),
        );
    }

    private static function registered(DomainEmployerRegistered $event): EmployerRegistered
    {
        $aggregateId = $event->accountId()->value();
        $cesuNumber = $event->cesuNumber()->value();
        $origin = $event->origin()->value;

        return new EmployerRegistered(
            IntegrationEventFacts::id(EmployerRegistered::TYPE, $aggregateId, $event->occurredAt(), [$cesuNumber, $origin]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $cesuNumber,
            $origin,
        );
    }

    private static function statusRefreshed(EmployerStatusRefreshed $event): EmployerStatusChanged
    {
        $aggregateId = $event->accountId()->value();
        $status = $event->status();
        $mandatePresent = $status->mandatePresent();
        $aiciActive = $status->aiciActive();
        $online = $status->rawOnlineAccountState();
        $sepa = $status->rawSepaMandateState();

        return new EmployerStatusChanged(
            IntegrationEventFacts::id(
                EmployerStatusChanged::TYPE,
                $aggregateId,
                $event->occurredAt(),
                [$event->previousStage()->value, $event->stage()->value, $mandatePresent ? '1' : '0', $aiciActive ? '1' : '0', $online, $sepa],
            ),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->cesuNumber()->value(),
            $event->previousStage()->value,
            $event->stage()->value,
            $mandatePresent,
            $aiciActive,
            $online,
            $sepa,
        );
    }

    private static function stageChanged(EmployerStageChanged $event): EmployerStatusChanged
    {
        $aggregateId = $event->accountId()->value();

        return new EmployerStatusChanged(
            IntegrationEventFacts::id(EmployerStatusChanged::TYPE, $aggregateId, $event->occurredAt(), [$event->from()->value, $event->to()->value]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->cesuNumber()->value(),
            $event->from()->value,
            $event->to()->value,
            null,
            null,
            null,
            null,
        );
    }
}
