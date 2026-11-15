<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;

/**
 * Verifies an employee with URSSAF (method 021, a read), by Cesu number or by NIR (hidden prompt, never printed).
 *
 * Source: lot 6 design §5.4, open point 6.
 */
#[AsCommand(name: 'urssaf:cesu:verify-employee', description: 'Verify an employee with URSSAF (method 021); writes nothing')]
final class VerifyEmployeeCommand extends Command
{
    private const ARGUMENT_CESU_NUMBER = 'cesu-number';
    private const OPTION_NIR = 'nir';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly VerifyEmployeeHandler $verify,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_CESU_NUMBER, InputArgument::OPTIONAL, 'Cesu number of the employee');
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        $this->addOption(self::OPTION_NIR, null, InputOption::VALUE_NONE, 'Give the NIR of the employee instead (asked in a hidden prompt)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $cesuNumber = CommandOptions::argument($input, self::ARGUMENT_CESU_NUMBER);
            $nir = $input->getOption(self::OPTION_NIR) === true;
            if (($cesuNumber !== null) === $nir) {
                throw new InvalidOptionException('Give either the cesu-number argument or --nir.');
            }
            $employee = $nir
                ? new EmployeeReferenceDraft(null, CommandPrompts::hiddenValue($input, $output, new QuestionHelper(), 'Employee NIR'), null)
                : new EmployeeReferenceDraft($cesuNumber, null, null);

            CommandReports::renderFields($output, CommandViews::employeeVerification(($this->verify)(new VerifyEmployee($tenant, $employee))));

            return Command::SUCCESS;
        });
    }
}
