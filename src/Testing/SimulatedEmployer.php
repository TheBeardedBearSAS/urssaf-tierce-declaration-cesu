<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Testing;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * An employer account of the simulated Cesu registry: states, mandate holder, Cesu+ pairs and AICI activation.
 *
 * Source: reference `SimulatedCustomer` (style), lot 5 design §4.1, §4.5.
 *
 * @internal
 */
final class SimulatedEmployer
{
    private ?TenantId $mandateHolder = null;

    private string $onlineAccountState = 'actif';

    private string $sepaMandateState = 'actif';

    private bool $aiciActive = false;

    /** @var array<string, CesuNumber> employees with an active Cesu+ pair, by Cesu number */
    private array $cesuPlusEmployees = [];

    public function __construct(
        public readonly CesuNumber $cesuNumber,
        private readonly CalendarDate $birthDate,
    ) {}

    /**
     * Same Cesu number and same birth date.
     */
    public function matches(Employer $employer): bool
    {
        return $this->cesuNumber->equals($employer->cesuNumber()) && $this->birthDate->equals($employer->birthDate());
    }

    public function holdBy(?TenantId $tenantId): void
    {
        $this->mandateHolder = $tenantId;
    }

    public function isMandatedBy(TenantId $tenantId): bool
    {
        return $this->mandateHolder?->equals($tenantId) ?? false;
    }

    /**
     * Whether another tenant than the given one holds the mandate.
     */
    public function isHeldByAnotherThan(TenantId $tenantId): bool
    {
        return $this->mandateHolder instanceof TenantId && !$this->mandateHolder->equals($tenantId);
    }

    public function setStates(string $onlineAccountState, string $sepaMandateState): void
    {
        $this->onlineAccountState = $onlineAccountState;
        $this->sepaMandateState = $sepaMandateState;
    }

    public function onlineAccountState(): string
    {
        return $this->onlineAccountState;
    }

    public function sepaMandateState(): string
    {
        return $this->sepaMandateState;
    }

    public function activateAici(): void
    {
        $this->aiciActive = true;
    }

    public function isAiciActive(): bool
    {
        return $this->aiciActive;
    }

    public function activateCesuPlus(CesuNumber $employee): void
    {
        $this->cesuPlusEmployees[$employee->value()] = $employee;
    }

    /**
     * Whether at least one employee has an active Cesu+ pair with the employer.
     */
    public function hasCesuPlus(): bool
    {
        return $this->cesuPlusEmployees !== [];
    }

    public function isCesuPlusActive(CesuNumber $employee): bool
    {
        return \array_key_exists($employee->value(), $this->cesuPlusEmployees);
    }
}
