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

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;

/**
 * Method 020 for one tracked employer. Every failure of the call propagates and changes nothing.
 */
final readonly class SyncEmployerStatusHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private EmployerStatusRefresher $refresher,
        private MandateRepository $mandates,
        private EmployerAccountRepository $accounts,
    ) {}

    /**
     * @throws ValidationFailed
     * @throws RecordNotFound
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(SyncEmployerStatus $command): EmployerAccountView
    {
        $employee = $command->employee instanceof EmployeeReferenceDraft ? EmployeeReferenceFactory::forLookup()->fromDraft($command->employee) : null;
        $account = $this->locator->locate($command->tenantId, $command->employer);
        $this->refresher->refresh($account, $employee);

        return EmployerAccountView::fromAccount(
            $this->accounts->get($account->id()),
            $this->mandates->findActive($account->tenantId(), $account->cesuNumber()),
        );
    }
}
