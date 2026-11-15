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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

/**
 * Tracks an employer that already has a Cesu account (no URSSAF call), so that its mandate and declarations can be
 * managed. The birth date is read from a hidden prompt and never printed (ADR 0007).
 *
 * Source: lot 6 design §5.4, open points 5 and 6.
 */
#[AsCommand(name: 'urssaf:cesu:employer:import', description: 'Track an existing Cesu employer (no URSSAF call)')]
final class ImportEmployerCommand extends Command
{
    private const ARGUMENT_CESU_NUMBER = 'cesu-number';
    private const OPTION_REFERENCE = 'reference';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly ImportEmployerHandler $import,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(self::ARGUMENT_CESU_NUMBER, InputArgument::REQUIRED, 'Cesu number of the employer');
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        $this->addOption(self::OPTION_REFERENCE, null, InputOption::VALUE_REQUIRED, 'Your reference of the employer (required)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $reference = CommandOptions::text($input, self::OPTION_REFERENCE) ?? throw new InvalidOptionException('The --reference option is required.');
            $cesuNumber = CesuNumber::fromString(CommandOptions::argument($input, self::ARGUMENT_CESU_NUMBER) ?? '');
            $birthDate = CalendarDate::fromString(CommandPrompts::hiddenValue($input, $output, new QuestionHelper(), 'Employer birth date (YYYY-MM-DD)'));

            $account = ($this->import)(new ImportEmployer($tenant, ExternalReference::fromString($reference), Employer::of($cesuNumber, $birthDate)));
            CommandReports::renderFields($output, CommandViews::trackedAccount($account));

            return Command::SUCCESS;
        });
    }
}
