<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Method 020 reported a first or a different employer status, or the stage changed. No birth date (ADR 0012).
 */
final readonly class EmployerStatusRefreshed implements DomainEvent
{
    public function __construct(
        private EmployerAccountId $accountId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private CesuNumber $cesuNumber,
        private EmployerStage $previousStage,
        private EmployerStage $stage,
        private EmployerStatus $status,
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

    public function previousStage(): EmployerStage
    {
        return $this->previousStage;
    }

    public function stage(): EmployerStage
    {
        return $this->stage;
    }

    public function status(): EmployerStatus
    {
        return $this->status;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
