<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event;

/**
 * URSSAF reflects the mandate: method 020 reported it present (ADR 0006, ADR 0012), the prerequisite of method 050.
 *
 * - aggregateId: the mandate id;
 * - externalReference: the reference of the employer in the host application;
 * - cesuNumber: the Cesu number of the employer.
 */
final readonly class MandateConfirmed extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.mandate.confirmed';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $cesuNumber,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
