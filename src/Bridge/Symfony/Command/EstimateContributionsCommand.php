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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\EstimateContributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\EstimateContributionsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ExemptionCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\GeographicZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SalaryType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedHourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Estimates the contributions of a home employment (PE) paid by the hour (method 040, a read). Foster carer estimates
 * and salary supplements need structured input: use EstimateContributionsHandler from the application.
 *
 * Source: lot 6 design §5.4, open point 14.
 */
#[AsCommand(name: 'urssaf:cesu:estimate', description: 'Estimate the contributions of a home employment paid by the hour (method 040)')]
final class EstimateContributionsCommand extends Command
{
    private const OPTION_HOURLY_WAGE = 'hourly-wage';
    private const OPTION_HOURS = 'hours';
    private const OPTION_FIRST_TIER = 'first-tier-overtime-hours';
    private const OPTION_SECOND_TIER = 'second-tier-overtime-hours';
    private const OPTION_PAID_LEAVE_HOURS = 'paid-leave-hours';
    private const OPTION_PAID_LEAVE_INCLUDED = 'paid-leave-included';
    private const OPTION_SALARY_TYPE = 'salary-type';
    private const OPTION_EXEMPTION = 'exemption';
    private const OPTION_ZONE = 'zone';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly EstimateContributionsHandler $estimate,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        $this->addOption(self::OPTION_HOURLY_WAGE, null, InputOption::VALUE_REQUIRED, 'Hourly wage in euros, e.g. 12.50 (required)');
        $this->addOption(self::OPTION_HOURS, null, InputOption::VALUE_REQUIRED, 'Worked hours (required)');
        $this->addOption(self::OPTION_FIRST_TIER, null, InputOption::VALUE_REQUIRED, 'First tier overtime hours');
        $this->addOption(self::OPTION_SECOND_TIER, null, InputOption::VALUE_REQUIRED, 'Second tier overtime hours');
        $this->addOption(self::OPTION_PAID_LEAVE_HOURS, null, InputOption::VALUE_REQUIRED, 'Paid leave hours');
        $this->addOption(self::OPTION_PAID_LEAVE_INCLUDED, null, InputOption::VALUE_NONE, 'The hourly wage includes the paid leave');
        $this->addOption(self::OPTION_SALARY_TYPE, null, InputOption::VALUE_REQUIRED, 'Salary type: NET or BRUT', SalaryType::Net->value);
        $this->addOption(self::OPTION_EXEMPTION, null, InputOption::VALUE_REQUIRED, 'Exemption code', ExemptionCode::NoExemption->value);
        $this->addOption(self::OPTION_ZONE, null, InputOption::VALUE_REQUIRED, 'Geographic zone code', GeographicZone::Metropolitan->value);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $hourlyWage = CommandOptions::text($input, self::OPTION_HOURLY_WAGE) ?? throw self::required(self::OPTION_HOURLY_WAGE);
            $hours = CommandOptions::integer($input, self::OPTION_HOURS) ?? throw self::required(self::OPTION_HOURS);
            $income = new SimulatedHourlyIncome(
                Money::fromDecimalString($hourlyWage),
                WorkedHours::of(
                    $hours,
                    CommandOptions::integer($input, self::OPTION_FIRST_TIER),
                    CommandOptions::integer($input, self::OPTION_SECOND_TIER),
                ),
                CommandOptions::integer($input, self::OPTION_PAID_LEAVE_HOURS),
                [],
                SalaryType::fromCode(CommandOptions::text($input, self::OPTION_SALARY_TYPE) ?? ''),
                ExemptionCode::fromCode(CommandOptions::text($input, self::OPTION_EXEMPTION) ?? ''),
                GeographicZone::fromCode(CommandOptions::text($input, self::OPTION_ZONE) ?? ''),
                $input->getOption(self::OPTION_PAID_LEAVE_INCLUDED) === true,
            );

            $contributions = ($this->estimate)(new EstimateContributions($tenant, EstimateRequest::of(EmploymentKind::HomeEmployment, $income, null)));
            CommandReports::renderFields($output, CommandViews::contributions($contributions));

            return Command::SUCCESS;
        });
    }

    private static function required(string $option): InvalidOptionException
    {
        return new InvalidOptionException(\sprintf('The --%s option is required.', $option));
    }
}
