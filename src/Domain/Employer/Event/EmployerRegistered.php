<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\RegistrationOrigin;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * An employer account is tracked (registered with method 010 or imported). No birth date (ADR 0012).
 */
final readonly class EmployerRegistered implements DomainEvent
{
    public function __construct(
        private EmployerAccountId $accountId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private CesuNumber $cesuNumber,
        private RegistrationOrigin $origin,
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

    public function origin(): RegistrationOrigin
    {
        return $this->origin;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
