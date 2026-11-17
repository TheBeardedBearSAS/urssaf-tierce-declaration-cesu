<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Local guard of the operations that need a mandate (050, 060; ADR 0009): refuses them before any URSSAF call unless
 * the employer has a non-cancelled local mandate, or the package never handled a mandate of the employer and its
 * tracked account reports the mandate present (mandated outside the package, last 020 answer). Skipped by the command
 * opt-out and by the policy ("requireLocalMandate").
 */
final readonly class MandateGuard
{
    public function __construct(
        private MandateRepository $mandates,
        private EmployerAccountRepository $accounts,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * The flag is the opt-out of the command ("skipMandateCheck", ADR 0009), passed through as is.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     *
     * @throws MandateRequired "mandate.required"
     */
    public function assertMandate(TenantId $tenantId, CesuNumber $employer, Operation $operation, bool $skipMandateCheck): void
    {
        if ($skipMandateCheck || !$this->policy->requireLocalMandate()) {
            return;
        }
        if ($this->mandates->findActive($tenantId, $employer) instanceof Mandate) {
            return;
        }
        if ($this->mandates->findByEmployer($tenantId, $employer) === [] && $this->reportedPresent($tenantId, $employer)) {
            return;
        }

        throw MandateRequired::forEmployer($employer, $operation);
    }

    private function reportedPresent(TenantId $tenantId, CesuNumber $employer): bool
    {
        $stage = $this->accounts->findByCesuNumber($tenantId, $employer)?->stage();

        return $stage === EmployerStage::MandateConfirmed || $stage === EmployerStage::AiciActive;
    }
}
