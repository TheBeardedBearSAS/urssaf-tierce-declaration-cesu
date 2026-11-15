<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status;

/**
 * Snapshot of the answer of method 020 for an employer (api-reference §2.2, §3.4, §4.3): "inscrit",
 * "etatCompteEnLigne", "etatMandatSepa", "mandatPresent", "etatCesuPlus", "motifBlocageCesuPlus", "etatAICI".
 * The public constructor is the factory (more than 5 parts). A snapshot is never refused by the domain.
 *
 * Unknown state codes are kept raw next to a nullable enum (ADR 0000). The blocking reason is trimmed and "" or
 * "null" (any case, §4.3) mean absent. The Cesu+ state is relative to an employer / employee pair (§5.9).
 */
final readonly class EmployerStatus
{
    private ?string $cesuPlusBlockingReason;

    public function __construct(
        private bool $registered,
        private ?string $onlineAccountState,
        private ?string $sepaMandateState,
        private bool $mandatePresent,
        private ?string $cesuPlusState,
        ?string $cesuPlusBlockingReason,
        private bool $aiciActive,
    ) {
        $reason = $cesuPlusBlockingReason === null ? null : trim($cesuPlusBlockingReason);
        $this->cesuPlusBlockingReason = $reason === '' || strtolower((string) $reason) === 'null' ? null : $reason;
    }

    public function registered(): bool
    {
        return $this->registered;
    }

    public function mandatePresent(): bool
    {
        return $this->mandatePresent;
    }

    public function aiciActive(): bool
    {
        return $this->aiciActive;
    }

    public function onlineAccountState(): ?OnlineAccountState
    {
        return $this->onlineAccountState === null ? null : OnlineAccountState::tryFromWire($this->onlineAccountState);
    }

    public function rawOnlineAccountState(): ?string
    {
        return $this->onlineAccountState;
    }

    public function sepaMandateState(): ?SepaMandateState
    {
        return $this->sepaMandateState === null ? null : SepaMandateState::tryFromWire($this->sepaMandateState);
    }

    public function rawSepaMandateState(): ?string
    {
        return $this->sepaMandateState;
    }

    public function cesuPlusState(): ?CesuPlusState
    {
        return $this->cesuPlusState === null ? null : CesuPlusState::tryFromWire($this->cesuPlusState);
    }

    public function rawCesuPlusState(): ?string
    {
        return $this->cesuPlusState;
    }

    public function cesuPlusBlockingReason(): ?string
    {
        return $this->cesuPlusBlockingReason;
    }

    public function isCesuPlusActive(): bool
    {
        return $this->cesuPlusState() === CesuPlusState::Active;
    }

    public function equals(self $other): bool
    {
        return $this->registered === $other->registered
            && $this->onlineAccountState === $other->onlineAccountState
            && $this->sepaMandateState === $other->sepaMandateState
            && $this->mandatePresent === $other->mandatePresent
            && $this->cesuPlusState === $other->cesuPlusState
            && $this->cesuPlusBlockingReason === $other->cesuPlusBlockingReason
            && $this->aiciActive === $other->aiciActive;
    }
}
