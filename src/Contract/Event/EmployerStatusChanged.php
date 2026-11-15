<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event;

/**
 * The tracked state of an employer changed: a method 020 refresh, or a local mandate change (030 / 031, ADR 0006,
 * ADR 0012).
 *
 * - aggregateId: the employer account id;
 * - previousStage, stage: the local employer stages (registered, mandate_notified, mandate_confirmed, aici_active,
 *   mandate_cancelled);
 * - mandatePresent, aiciActive, onlineAccountState, sepaMandateState: the raw 020 answer, null when the change comes
 *   from a local mandate change (no 020 answer).
 */
final readonly class EmployerStatusChanged extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.employer.status_changed';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $cesuNumber,
        public string $previousStage,
        public string $stage,
        public ?bool $mandatePresent,
        public ?bool $aiciActive,
        public ?string $onlineAccountState,
        public ?string $sepaMandateState,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
