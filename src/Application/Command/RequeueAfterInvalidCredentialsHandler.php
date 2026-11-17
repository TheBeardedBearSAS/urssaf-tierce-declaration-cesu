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

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\RequeueReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Once the tenant credentials are fixed: moves up to "limit" declarations from invalid_credentials back to pending
 * (one transaction). The host then declares them again with DeclareSalary (it holds the payload, ADR 0007). Run it
 * again while the returned count reaches the limit.
 */
final readonly class RequeueAfterInvalidCredentialsHandler
{
    public function __construct(
        private DeclarationRecordRepository $records,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    public function __invoke(RequeueAfterInvalidCredentials $command): RequeueReport
    {
        $declarations = $this->records->findByState(
            $command->tenantId,
            DeclarationState::InvalidCredentials,
            $this->policy->pageSize($command->limit),
        );

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($declarations): RequeueReport {
            $now = Instant::fromDateTime($this->clock->now());
            foreach ($declarations as $declaration) {
                $declaration->retry($now, $this->policy->maxSubmissionAttempts());
                $this->records->save($declaration);
                $events->record(...$declaration->releaseEvents());
            }

            return new RequeueReport(\count($declarations));
        });
    }
}
