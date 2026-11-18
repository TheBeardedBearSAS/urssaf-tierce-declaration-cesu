<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\MandateGuard;

/**
 * Method 060 behind the mandate guard (ADR 0009). Nothing is stored and the call is never resent by the package: every
 * failure propagates, an unknown outcome included (an operator checks the Cesu+ state with method 020).
 */
final readonly class ActivateCesuPlusHandler
{
    public function __construct(private CesuPlusActivator $activator, private MandateGuard $guard) {}

    /**
     * @throws MandateRequired
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     * @throws OutcomeUnknown
     */
    public function __invoke(ActivateCesuPlus $command): CesuPlusActivation
    {
        $this->guard->assertMandate(
            $command->tenantId,
            $command->employer->cesuNumber(),
            Operation::ActivateCesuPlus,
            $command->skipMandateCheck,
        );

        return $this->activator->activateCesuPlus(
            $command->tenantId,
            CesuPlusActivationRequest::of($command->employer, $command->employeeCesuNumber, $command->employeeAgreement),
        );
    }
}
