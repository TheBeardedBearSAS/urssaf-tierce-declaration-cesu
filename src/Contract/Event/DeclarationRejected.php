<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event;

/**
 * URSSAF refused the declaration and recorded nothing (final, ADR 0006).
 *
 * - aggregateId: the declaration record id;
 * - errorCodes: the URSSAF error codes, never their description.
 */
final readonly class DeclarationRejected extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.declaration.rejected';

    /**
     * @param list<string> $errorCodes
     */
    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public array $errorCodes,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
