<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Aggregate root: local follow-up of an employer account (ADR 0006, 0010). Created with {@see self::register()} (after
 * method 010) or {@see self::import()} (existing Cesu employer).
 *
 * The identity (id, tenant, external reference, employer, origin, creation instant) is immutable; the mutable part is
 * the {@see EmployerTracking}. Stages follow URSSAF (method 020, 030, 031) and never block a call: there is no invalid
 * transition. Every notable change records a domain event, see {@see self::releaseEvents()}.
 *
 * Not final only because Doctrine generates lazy-loading proxies by extending the mapped entities: final by
 * convention, never extend it. For the same reason the identifier is neither readonly nor private: a proxy is created
 * with its identifier, which Doctrine assigns again (another instance of the same value) when the proxy is loaded. It is
 * never reassigned otherwise.
 *
 * The root exposes its identity, transitions and queries, hence the method count.
 *
 * @final
 *
 * @mago-expect lint:too-many-methods
 */
class EmployerAccount
{
    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        protected EmployerAccountId $id,
        private readonly TenantId $tenantId,
        private readonly ExternalReference $externalReference,
        private readonly Employer $employer,
        private readonly RegistrationOrigin $origin,
        private readonly Instant $createdAt,
        private EmployerTracking $tracking,
    ) {}

    /**
     * Employer registered by method 010 through this package. Records {@see EmployerRegistered}.
     */
    public static function register(
        EmployerAccountId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        Employer $employer,
        Instant $now,
    ): self {
        return (new self(
            $id,
            $tenantId,
            $externalReference,
            $employer,
            RegistrationOrigin::Registered,
            $now,
            new EmployerTracking(EmployerStage::Registered, null, null, null, null, $now),
        ))->recordRegistration();
    }

    /**
     * Existing Cesu employer adopted by the platform. Records {@see EmployerRegistered}.
     */
    public static function import(
        EmployerAccountId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        Employer $employer,
        Instant $now,
    ): self {
        return (new self(
            $id,
            $tenantId,
            $externalReference,
            $employer,
            RegistrationOrigin::Imported,
            $now,
            new EmployerTracking(EmployerStage::Registered, null, null, null, null, $now),
        ))->recordRegistration();
    }

    public function id(): EmployerAccountId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    public function employer(): Employer
    {
        return $this->employer;
    }

    public function cesuNumber(): CesuNumber
    {
        return $this->employer->cesuNumber();
    }

    public function birthDate(): CalendarDate
    {
        return $this->employer->birthDate();
    }

    public function origin(): RegistrationOrigin
    {
        return $this->origin;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function tracking(): EmployerTracking
    {
        return $this->tracking;
    }

    public function stage(): EmployerStage
    {
        return $this->tracking->stage();
    }

    public function lastStatus(): ?EmployerStatus
    {
        return $this->tracking->lastStatus();
    }

    /**
     * Kind of the employee reference the last status was read for, if any (the Cesu+ state is relative to a pair).
     */
    public function lastStatusEmployeeKind(): ?EmployeeReferenceKind
    {
        return $this->tracking->lastStatusEmployeeKind();
    }

    /**
     * Cesu number of the employee the last status was read for, when the reference carried one (never a NIR, ADR 0007).
     */
    public function lastStatusEmployeeCesuNumber(): ?CesuNumber
    {
        return $this->tracking->lastStatusEmployeeCesuNumber();
    }

    public function lastCheckedAt(): ?Instant
    {
        return $this->tracking->lastCheckedAt();
    }

    public function updatedAt(): Instant
    {
        return $this->tracking->updatedAt();
    }

    /**
     * Method 030 succeeded. Records {@see EmployerStageChanged} when the stage changes.
     */
    public function markMandateNotified(Instant $now): void
    {
        $this->changeStage($this->tracking->stage()->afterMandateNotified(), $now);
    }

    /**
     * Method 031 succeeded. Records {@see EmployerStageChanged} when the stage changes.
     */
    public function markMandateCancelled(Instant $now): void
    {
        $this->changeStage($this->tracking->stage()->afterMandateCancelled(), $now);
    }

    /**
     * Method 020 answered, optionally for an employee (Cesu+ state of the pair). Records
     * {@see EmployerStatusRefreshed} on the first snapshot, when the snapshot or its employee kind and Cesu number differ from the last
     * one, or when the stage changes; otherwise only the check instants are refreshed.
     */
    public function applyStatus(EmployerStatus $status, ?EmployeeReference $employee, Instant $now): void
    {
        $previousStage = $this->tracking->stage();
        $stage = $previousStage->afterStatus($status);
        $employeeKind = $employee?->kind();
        $employeeCesuNumber = $employee?->cesuNumber();
        $refreshed = !$this->tracking->hasSnapshot($status, $employeeKind, $employeeCesuNumber) || $stage !== $previousStage;
        $this->tracking = $this->tracking->withStatus($status, $employeeKind, $employeeCesuNumber, $stage, $now);
        if ($refreshed) {
            $this->events[] = new EmployerStatusRefreshed(
                $this->id,
                $this->tenantId,
                $this->externalReference,
                $this->cesuNumber(),
                $previousStage,
                $stage,
                $status,
                $now,
            );
        }
    }

    /**
     * Returns the events recorded since the last call, and forgets them.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    private function recordRegistration(): self
    {
        $this->events[] = new EmployerRegistered(
            $this->id,
            $this->tenantId,
            $this->externalReference,
            $this->cesuNumber(),
            $this->origin,
            $this->createdAt,
        );

        return $this;
    }

    private function changeStage(EmployerStage $stage, Instant $now): void
    {
        $from = $this->tracking->stage();
        $this->tracking = $this->tracking->withStage($stage, $now);
        if ($stage !== $from) {
            $this->events[] = new EmployerStageChanged(
                $this->id,
                $this->tenantId,
                $this->externalReference,
                $this->cesuNumber(),
                $from,
                $stage,
                $now,
            );
        }
    }
}
