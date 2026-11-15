<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event;

/**
 * The outcome of the declaration is unknown: it was never resent and needs a manual review (ADR 0003).
 *
 * - aggregateId: the declaration record id;
 * - reason: "may_have_reached_urssaf" or "stale_submission".
 */
final readonly class DeclarationOutcomeUnknown extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.declaration.outcome_unknown';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $reason,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
