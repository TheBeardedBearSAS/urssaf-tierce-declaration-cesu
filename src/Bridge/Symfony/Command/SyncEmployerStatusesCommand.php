<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncAllEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncAllEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Refreshes the tracked employers from URSSAF (method 020, employer only: a read): one employer given by a selector,
 * the least recently checked employers of a tenant, or of every tenant. `--dry-run` lists what would be refreshed;
 * `--async` dispatches one message per tenant instead (Messenger).
 *
 * Source: reference SyncPaymentStatusesCommand, lot 6 design §5.4, §7.3, open points 2 and 20.
 */
#[AsCommand(name: 'urssaf:cesu:employers:sync-status', description: 'Refresh the status of the tracked employers (method 020, employer only)')]
final class SyncEmployerStatusesCommand extends Command
{
    private const OPTION_ASYNC = 'async';

    public function __construct(
        private readonly EmployerAccountRepository $accounts,
        private readonly SyncEmployerStatusHandler $syncOne,
        private readonly SyncEmployerStatusesHandler $syncTenant,
        private readonly SyncAllEmployerStatusesHandler $syncAll,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
        private readonly ?MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: every tenant; required with an employer selector)');
        CommandSelectors::addEmployerSelector($this);
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
        $this->addOption(self::OPTION_ASYNC, null, InputOption::VALUE_NONE, 'Dispatch one Messenger message per tenant instead of refreshing now');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $selector = CommandSelectors::optionalEmployerSelector($input);
            $limit = CommandOptions::limit($input);
            $dryRun = CommandOptions::dryRun($input);
            $async = $input->getOption(self::OPTION_ASYNC) === true;
            if ($selector instanceof EmployerSelector) {
                if ($limit !== null || $dryRun || $async) {
                    throw new InvalidOptionException('An employer selector cannot be combined with --limit, --dry-run or --async.');
                }

                return $this->refreshOne($input, $output, $selector);
            }
            if ($async) {
                return $dryRun ? throw new InvalidOptionException('--async cannot be combined with --dry-run.') : $this->dispatch($input, $output);
            }
            if ($dryRun) {
                return $this->plan($input, $output, $limit);
            }
            $tenant = CommandOptions::tenant($input);

            return $this->locks->exclusively(
                'employers.sync-status',
                $tenant instanceof TenantId ? $tenant->value() : '*',
                fn(): int => CommandReports::renderReport($output, $tenant instanceof TenantId
                    ? ($this->syncTenant)(new SyncEmployerStatuses($tenant, $limit))
                    : ($this->syncAll)(new SyncAllEmployerStatuses($limit))),
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }

    private function refreshOne(InputInterface $input, OutputInterface $output, EmployerSelector $selector): int
    {
        $tenant = $this->requiredTenant($input);

        return $this->locks->exclusively(
            'employer.sync-status',
            $selector->reference->value(),
            function () use ($output, $tenant, $selector): int {
                CommandReports::renderFields($output, CommandViews::refreshedAccount(($this->syncOne)(new SyncEmployerStatus($tenant, $selector, null))));

                return Command::SUCCESS;
            },
            static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
        );
    }

    private function dispatch(InputInterface $input, OutputInterface $output): int
    {
        $bus = $this->bus ?? throw new InvalidOptionException('--async requires "messenger.enabled".');
        $tenants = $this->tenants($input);
        foreach ($tenants as $tenant) {
            $bus->dispatch(new SyncEmployerStatusesMessage($tenant->value()));
        }
        $output->writeln(\sprintf('%d message(s) dispatched.', \count($tenants)));

        return Command::SUCCESS;
    }

    private function plan(InputInterface $input, OutputInterface $output, ?int $limit): int
    {
        $rows = [];
        foreach ($this->tenants($input) as $tenant) {
            foreach ($this->accounts->findLeastRecentlyChecked($tenant, $this->policy->pageSize($limit)) as $account) {
                $rows[] = [$tenant->value(), $account->id()->value()];
            }
        }

        return CommandReports::renderPlan($output, $rows);
    }

    private function requiredTenant(InputInterface $input): TenantId
    {
        return CommandOptions::tenant($input) ?? throw new InvalidOptionException('The --tenant option is required with an employer selector.');
    }

    /**
     * @return list<TenantId> the --tenant, else every tenant with tracked employers
     */
    private function tenants(InputInterface $input): array
    {
        $tenant = CommandOptions::tenant($input);

        return $tenant instanceof TenantId ? [$tenant] : $this->accounts->tenants();
    }
}
