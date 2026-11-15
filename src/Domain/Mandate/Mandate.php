<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateNotified;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Aggregate root: local follow-up of the mandate given to the platform by an employer (methods 030, 031, 020; ADR
 * 0006). Created with {@see self::notify()}; a new notification after a cancellation is a new mandate (the
 * application decides).
 *
 * The state machine is {@see MandateState}; invalid transitions throw {@see InvalidStateTransition}
 * ("mandate.invalid_state_transition"). Every state change records a domain event, see {@see self::releaseEvents()}.
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
class Mandate
{
    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        protected MandateId $id,
        private readonly TenantId $tenantId,
        private readonly ExternalReference $externalReference,
        private readonly Employer $employer,
        private MandateState $state,
        private MandateTimeline $timeline,
        private ?MandatePresenceCheck $lastCheck,
    ) {}

    /**
     * After a successful method 030: state "notified". Records {@see MandateNotified}.
     */
    public static function notify(
        MandateId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        Employer $employer,
        Instant $now,
    ): self {
        $mandate = new self(
            $id,
            $tenantId,
            $externalReference,
            $employer,
            MandateState::Notified,
            MandateTimeline::notified($now),
            null,
        );
        $mandate->events[] = new MandateNotified($id, $tenantId, $externalReference, $employer->cesuNumber(), $now);

        return $mandate;
    }

    public function id(): MandateId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * Host reference of the employer, carried by the integration events (ADR 0012).
     */
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

    public function state(): MandateState
    {
        return $this->state;
    }

    public function timeline(): MandateTimeline
    {
        return $this->timeline;
    }

    public function notifiedAt(): Instant
    {
        return $this->timeline->notifiedAt();
    }

    public function confirmedAt(): ?Instant
    {
        return $this->timeline->confirmedAt();
    }

    public function cancelledAt(): ?Instant
    {
        return $this->timeline->cancelledAt();
    }

    public function lastCheck(): ?MandatePresenceCheck
    {
        return $this->lastCheck;
    }

    public function updatedAt(): Instant
    {
        return $this->timeline->updatedAt();
    }

    /**
     * Not cancelled (used by the mandate guard, ADR 0009).
     */
    public function isActive(): bool
    {
        return $this->state->isActive();
    }

    /**
     * Applies the "mandatPresent" answer of method 020 and remembers it. Present: notified -> confirmed (records
     * {@see MandateConfirmed}), confirmed stays (a refresh is not a transition), cancelled is refused. Absent: never
     * changes the state (only method 031 cancels).
     *
     * @throws InvalidStateTransition "mandate.invalid_state_transition" when present is reported for a cancelled mandate
     */
    public function applyStatus(bool $mandatePresent, Instant $now): void
    {
        $confirms = $mandatePresent && $this->state !== MandateState::Confirmed;
        $state = $confirms ? $this->state->transitionTo(MandateState::Confirmed) : $this->state;
        $this->state = $state;
        $this->lastCheck = MandatePresenceCheck::of($mandatePresent, $now);
        $this->timeline = $confirms ? $this->timeline->withConfirmation($now) : $this->timeline->touched($now);
        if ($confirms) {
            $this->events[] = new MandateConfirmed($this->id, $this->tenantId, $this->externalReference, $this->cesuNumber(), $now);
        }
    }

    /**
     * Method 031 succeeded: notified | confirmed -> cancelled. Records {@see MandateCancelled}.
     *
     * @throws InvalidStateTransition "mandate.invalid_state_transition" when already cancelled
     */
    public function cancel(Instant $now): void
    {
        $this->state = $this->state->transitionTo(MandateState::Cancelled);
        $this->timeline = $this->timeline->withCancellation($now);
        $this->events[] = new MandateCancelled($this->id, $this->tenantId, $this->externalReference, $this->cesuNumber(), $now);
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
}
