<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\CancelMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\CancelMandateHandler;

/**
 * Cancels the mandate of a tracked employer at URSSAF (method 031), after a confirmation (or --force).
 *
 * Source: lot 6 design §5.4.
 */
#[AsCommand(name: 'urssaf:cesu:mandate:cancel', description: 'Cancel the mandate of a tracked employer at URSSAF (method 031)')]
final class CancelMandateCommand extends Command
{
    private const OPTION_FORCE = 'force';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly CancelMandateHandler $cancel,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandSelectors::addEmployerSelector($this);
        $this->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Do not ask for a confirmation (required when not interactive)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $selector = CommandSelectors::employerSelector($input);
            if ($input->getOption(self::OPTION_FORCE) !== true) {
                if (!$input->isInteractive()) {
                    throw new InvalidOptionException('Cancelling a mandate needs a confirmation: use --force when not interactive.');
                }
                $question = new ConfirmationQuestion(\sprintf('Cancel the mandate of employer %s at URSSAF? [y/N] ', $selector->reference->value()), false);
                if ((new QuestionHelper())->ask($input, $output, $question) !== true) {
                    $output->writeln('Aborted: nothing was sent to URSSAF.');

                    return Command::SUCCESS;
                }
            }

            CommandReports::renderFields($output, CommandViews::mandate(($this->cancel)(new CancelMandate($tenant, $selector))));

            return Command::SUCCESS;
        });
    }
}
