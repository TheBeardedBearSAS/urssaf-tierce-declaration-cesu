<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message\SyncEmployerStatusMessage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Refreshes one tracked employer (method 020, employer only). Lot 6 design 7.2, lock 7.3.
 */
final readonly class SyncEmployerStatusMessageHandler
{
    public function __construct(private SyncEmployerStatusHandler $sync, private OperationLocks $locks) {}

    public function __invoke(SyncEmployerStatusMessage $message): void
    {
        $this->locks->exclusively(
            'employer.sync-status',
            $message->employerAccountId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->sync)(new SyncEmployerStatus(
                TenantId::fromString($message->tenantId),
                EmployerSelector::byAccountId(EmployerAccountId::fromString($message->employerAccountId)),
                null,
            ))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
