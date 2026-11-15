<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Contract\Event;

use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;

/**
 * Test double: an integration event with one extra property, its own type and version.
 */
final readonly class SampleIntegrationEvent extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.sample';
    public const VERSION = 2;

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $state,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
