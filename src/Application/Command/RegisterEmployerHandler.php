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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerRegistrar;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Method 010, synchronous (ADR 0007): the draft is validated locally, URSSAF creates the account and answers the Cesu
 * number, then the account is tracked. Every failure of the call propagates and stores nothing: a rejection (the host
 * imports an existing account when {@see UrssafRequestRejected::isEmailAlreadyKnown()}), invalid credentials, a
 * temporary failure (safe to call again) or an unknown outcome (an operator checks the account, then imports it).
 *
 * An external reference already tracked is refused without any call: the details cannot be compared (the bank details
 * are not stored) and a second 010 would hit the e-mail uniqueness.
 */
final readonly class RegisterEmployerHandler
{
    public function __construct(
        private EmployerAccountRepository $accounts,
        private EmployerRegistrar $registrar,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private NewEmployerFactory $factory = new NewEmployerFactory(),
    ) {}

    /**
     * @throws ValidationFailed
     * @throws EmployerAlreadyTracked
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     * @throws OutcomeUnknown
     */
    public function __invoke(RegisterEmployer $command): EmployerAccountView
    {
        $newEmployer = $this->factory->fromDraft($command->employer);
        $existing = $this->accounts->findByExternalReference($command->tenantId, $command->externalReference);
        if ($existing instanceof EmployerAccount) {
            throw EmployerAlreadyTracked::withExternalReference($command->externalReference, $existing->id());
        }

        $cesuNumber = $this->registrar->registerEmployer($command->tenantId, $newEmployer);
        $tracked = $this->accounts->findByCesuNumber($command->tenantId, $cesuNumber);
        if ($tracked instanceof EmployerAccount) {
            throw EmployerAlreadyTracked::withCesuNumber($cesuNumber, $tracked->id());
        }

        $account = $this->unitOfWork->run(function (RecordedEvents $events) use ($command, $newEmployer, $cesuNumber): EmployerAccount {
            $account = EmployerAccount::register(
                EmployerAccountId::fromString($this->ids->generate()),
                $command->tenantId,
                $command->externalReference,
                Employer::of($cesuNumber, $newEmployer->birthDate()),
                Instant::fromDateTime($this->clock->now()),
            );
            $this->accounts->save($account);
            $events->record(...$account->releaseEvents());

            return $account;
        });

        return EmployerAccountView::fromAccount($account, null);
    }
}
