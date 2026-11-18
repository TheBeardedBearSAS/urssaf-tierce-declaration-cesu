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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Tracks an existing Cesu employer. Idempotent: the same external reference with the same employer returns the tracked
 * account; the same reference with another employer, or a Cesu number tracked under another reference, is refused.
 */
final readonly class ImportEmployerHandler
{
    public function __construct(
        private EmployerAccountRepository $accounts,
        private MandateRepository $mandates,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws EmployerAlreadyTracked
     */
    public function __invoke(ImportEmployer $command): EmployerAccountView
    {
        $existing = $this->accounts->findByExternalReference($command->tenantId, $command->externalReference);
        if ($existing instanceof EmployerAccount) {
            return $this->replay($command, $existing);
        }
        $tracked = $this->accounts->findByCesuNumber($command->tenantId, $command->employer->cesuNumber());
        if ($tracked instanceof EmployerAccount) {
            throw EmployerAlreadyTracked::withCesuNumber($command->employer->cesuNumber(), $tracked->id());
        }

        $account = $this->unitOfWork->run(function (RecordedEvents $events) use ($command): EmployerAccount {
            $account = EmployerAccount::import(
                EmployerAccountId::fromString($this->ids->generate()),
                $command->tenantId,
                $command->externalReference,
                $command->employer,
                Instant::fromDateTime($this->clock->now()),
            );
            $this->accounts->save($account);
            $events->record(...$account->releaseEvents());

            return $account;
        });

        return EmployerAccountView::fromAccount($account, null);
    }

    /**
     * @throws EmployerAlreadyTracked when the tracked account belongs to another employer
     */
    private function replay(ImportEmployer $command, EmployerAccount $existing): EmployerAccountView
    {
        if (!$existing->employer()->equals($command->employer)) {
            throw EmployerAlreadyTracked::withExternalReference($command->externalReference, $existing->id());
        }

        return EmployerAccountView::fromAccount(
            $existing,
            $this->mandates->findActive($command->tenantId, $existing->cesuNumber()),
        );
    }
}
