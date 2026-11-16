<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * The employer account, its mandates and its declaration records were erased from the local store (GDPR, ADR 0015,
 * lot 10 design §4.1). Nothing was sent to URSSAF. No birth date (ADR 0012).
 */
final readonly class EmployerForgotten implements DomainEvent
{
    /**
     * @param list<MandateId>           $mandateIds
     * @param list<DeclarationRecordId> $declarationRecordIds
     */
    public function __construct(
        private EmployerAccountId $accountId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private CesuNumber $cesuNumber,
        private array $mandateIds,
        private array $declarationRecordIds,
        private Instant $occurredAt,
    ) {}

    public function accountId(): EmployerAccountId
    {
        return $this->accountId;
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

    /**
     * @return list<MandateId>
     */
    public function mandateIds(): array
    {
        return $this->mandateIds;
    }

    /**
     * @return list<DeclarationRecordId>
     */
    public function declarationRecordIds(): array
    {
        return $this->declarationRecordIds;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
