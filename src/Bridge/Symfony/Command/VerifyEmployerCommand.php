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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Verifies an employer account with URSSAF (method 020, a read: nothing is written). The employer birth date and the
 * employee NIR are read from hidden prompts and never printed (ADR 0007).
 *
 * Source: lot 6 design §5.4, open point 6.
 */
#[AsCommand(name: 'urssaf:cesu:verify-employer', description: 'Verify an employer account with URSSAF (method 020); writes nothing')]
final class VerifyEmployerCommand extends Command
{
    private const ARGUMENT_CESU_NUMBER = 'cesu-number';
    private const OPTION_EMPLOYEE_CESU_NUMBER = 'employee-cesu-number';
    private const OPTION_EMPLOYEE_NIR = 'employee-nir';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly VerifyEmployerHandler $verify,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_CESU_NUMBER, InputArgument::REQUIRED, 'Cesu number of the employer');
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        $this->addOption(self::OPTION_EMPLOYEE_CESU_NUMBER, null, InputOption::VALUE_REQUIRED, 'Also check this employee, given by Cesu number');
        $this->addOption(self::OPTION_EMPLOYEE_NIR, null, InputOption::VALUE_NONE, 'Also check an employee given by NIR (asked in a hidden prompt)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $cesuNumber = CesuNumber::fromString(CommandOptions::argument($input, self::ARGUMENT_CESU_NUMBER) ?? '');
            $employeeCesuNumber = CommandOptions::text($input, self::OPTION_EMPLOYEE_CESU_NUMBER);
            $employeeNir = $input->getOption(self::OPTION_EMPLOYEE_NIR) === true;
            if ($employeeCesuNumber !== null && $employeeNir) {
                throw new InvalidOptionException('Give either --employee-cesu-number or --employee-nir, not both.');
            }
            $helper = new QuestionHelper();
            $birthDate = CalendarDate::fromString(CommandPrompts::hiddenValue($input, $output, $helper, 'Employer birth date (YYYY-MM-DD)'));
            $employee = match (true) {
                $employeeNir => new EmployeeReferenceDraft(null, CommandPrompts::hiddenValue($input, $output, $helper, 'Employee NIR'), null),
                $employeeCesuNumber !== null => new EmployeeReferenceDraft($employeeCesuNumber, null, null),
                default => null,
            };

            $status = ($this->verify)(new VerifyEmployer($tenant, Employer::of($cesuNumber, $birthDate), $employee));
            CommandReports::renderFields($output, CommandViews::employerStatus($status));

            return Command::SUCCESS;
        });
    }
}
