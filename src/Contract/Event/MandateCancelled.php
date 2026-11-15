<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event;

/**
 * The mandate was cancelled (method 031, ADR 0006).
 *
 * - aggregateId: the mandate id;
 * - externalReference: the reference of the employer in the host application;
 * - cesuNumber: the Cesu number of the employer.
 */
final readonly class MandateCancelled extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.mandate.cancelled';

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
