<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Method 020 reported the mandate as present. No birth date (ADR 0012).
 */
final readonly class MandateConfirmed implements DomainEvent
{
    public function __construct(
        private MandateId $mandateId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private CesuNumber $cesuNumber,
        private Instant $occurredAt,
    ) {}

    public function mandateId(): MandateId
    {
        return $this->mandateId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    public function cesuNumber(): CesuNumber
    {
        return $this->cesuNumber;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
