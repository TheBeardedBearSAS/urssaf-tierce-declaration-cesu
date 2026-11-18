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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ResolveDeclarationOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ResolveDeclarationOutcomeHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;

/**
 * Settles a declaration left in "outcome_unknown" after the operator checked the outcome with URSSAF (ADR 0003): either
 * URSSAF has it (give its idDeclaration), or it never arrived (send it again with DeclareSalary).
 *
 * Source: lot 6 design §5.4.
 */
#[AsCommand(name: 'urssaf:cesu:declarations:resolve', description: 'Settle an outcome_unknown declaration after a manual check (ADR 0003)')]
final class ResolveDeclarationOutcomeCommand extends Command
{
    private const OPTION_DECLARED_AS = 'declared-as';
    private const OPTION_NOT_RECEIVED = 'not-received';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly ResolveDeclarationOutcomeHandler $resolve,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandSelectors::addDeclarationSelector($this);
        $this->addOption(self::OPTION_DECLARED_AS, null, InputOption::VALUE_REQUIRED, 'The idDeclaration URSSAF gave to the declaration');
        $this->addOption(self::OPTION_NOT_RECEIVED, null, InputOption::VALUE_NONE, 'URSSAF never received the declaration');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $selector = CommandSelectors::declarationSelector($input);
            $declaredAs = CommandOptions::text($input, self::OPTION_DECLARED_AS);
            $notReceived = $input->getOption(self::OPTION_NOT_RECEIVED) === true;
            if (($declaredAs !== null) === $notReceived) {
                throw new InvalidOptionException('Give one of --declared-as or --not-received.');
            }

            $declaration = ($this->resolve)($declaredAs !== null
                ? ResolveDeclarationOutcome::asDeclared($tenant, $selector, DeclarationId::fromString($declaredAs))
                : ResolveDeclarationOutcome::asNotReceived($tenant, $selector));
            CommandReports::renderFields($output, CommandViews::declaration($declaration));
            if ($notReceived) {
                $output->writeln('Send the declaration again with DeclareSalary (same external reference and content).');
            }

            return Command::SUCCESS;
        });
    }
}
