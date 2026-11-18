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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ReconcileStaleDeclarations;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Moves the declarations left "submitting" to manual review: they are never resent (ADR 0003). Exits with 1 when a
 * record was moved to manual review (an alert for the operator).
 *
 * Source: reference ReconcileStaleSubmissionsCommand, lot 6 design §5.4.
 */
#[AsCommand(name: 'urssaf:cesu:declarations:reconcile-stale', description: 'Move the declarations left submitting to manual review (never resent, ADR 0003)')]
final class ReconcileStaleDeclarationsCommand extends Command
{
    public function __construct(
        private readonly DeclarationRecordRepository $declarations,
        private readonly ReconcileStaleDeclarationsHandler $reconcile,
        private readonly Clock $clock,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $limit = CommandOptions::limit($input);
            if (CommandOptions::dryRun($input)) {
                $olderThan = Instant::fromDateTime($this->clock->now()->sub($this->policy->staleSubmissionAfter()));

                return CommandReports::renderPlan($output, array_map(
                    static fn(DeclarationRecord $record): array => [$record->tenantId()->value(), $record->id()->value()],
                    $this->declarations->findStaleSubmissions($olderThan, $this->policy->pageSize($limit)),
                ));
            }

            return $this->locks->exclusively(
                'declarations.reconcile-stale',
                '*',
                fn(): int => CommandReports::renderReport($output, ($this->reconcile)(new ReconcileStaleDeclarations($limit))),
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
