<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RetryableException;

/**
 * Polls method 020 (employer only) for the least recently checked employers of a tenant, never checked first, at most
 * "limit" in total. Per account (each one in its own transaction):
 * - answer applied -> {@see ItemOutcome::Changed} when it recorded an event, {@see ItemOutcome::Unchanged} otherwise;
 * - request refused by URSSAF -> {@see ItemOutcome::Failed} with the error codes, the account is untouched.
 * Credential failures ({@see UrssafAuthenticationFailed}) and temporary failures ({@see RetryableException}) stop the
 * run and propagate: what was already processed stays committed.
 */
final readonly class SyncEmployerStatusesHandler
{
    public function __construct(
        private EmployerAccountRepository $accounts,
        private EmployerStatusRefresher $refresher,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws UrssafAuthenticationFailed
     * @throws RetryableException
     */
    public function __invoke(SyncEmployerStatuses $command): BatchReport
    {
        $accounts = $this->accounts->findLeastRecentlyChecked($command->tenantId, $this->policy->pageSize($command->limit));

        return BatchReport::of(...array_map($this->sync(...), $accounts));
    }

    private function sync(EmployerAccount $account): BatchItem
    {
        $subject = $account->id()->value();

        try {
            $changed = $this->refresher->refresh($account, null);
        } catch (UrssafRequestRejected $rejection) {
            return new BatchItem($subject, ItemOutcome::Failed, $rejection->errors()->codes());
        }

        return new BatchItem($subject, $changed ? ItemOutcome::Changed : ItemOutcome::Unchanged);
    }
}
