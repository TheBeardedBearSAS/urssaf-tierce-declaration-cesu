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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

/**
 * The options that designate an employer or a declaration, and their reading. The values go through the value objects:
 * an invalid one is an invalid input (exit code 2).
 *
 * Source: lot 6 design §5.1.
 */
final class CommandSelectors
{
    private const OPTION_EMPLOYER = 'employer';
    private const OPTION_CESU_NUMBER = 'cesu-number';
    private const OPTION_ACCOUNT_ID = 'account-id';
    private const OPTION_DECLARATION = 'declaration';
    private const OPTION_RECORD_ID = 'record-id';

    /**
     * --employer=<external reference> | --cesu-number=<Cesu number> | --account-id=<account id>
     */
    public static function addEmployerSelector(Command $command): void
    {
        $command->addOption(self::OPTION_EMPLOYER, null, InputOption::VALUE_REQUIRED, 'External reference of the employer');
        $command->addOption(self::OPTION_CESU_NUMBER, null, InputOption::VALUE_REQUIRED, 'Cesu number of the employer');
        $command->addOption(self::OPTION_ACCOUNT_ID, null, InputOption::VALUE_REQUIRED, 'Id of the employer account');
    }

    /**
     * --declaration=<external reference> | --record-id=<record id>
     */
    public static function addDeclarationSelector(Command $command): void
    {
        $command->addOption(self::OPTION_DECLARATION, null, InputOption::VALUE_REQUIRED, 'External reference of the declaration');
        $command->addOption(self::OPTION_RECORD_ID, null, InputOption::VALUE_REQUIRED, 'Id of the declaration record');
    }

    /**
     * Exactly one of --employer, --cesu-number and --account-id.
     *
     * @throws InvalidOptionException when none or several are given
     */
    public static function employerSelector(InputInterface $input): EmployerSelector
    {
        return self::optionalEmployerSelector($input) ?? throw self::employerSelectorRequired();
    }

    /**
     * Same, null when none is given.
     *
     * @throws InvalidOptionException when several are given
     */
    public static function optionalEmployerSelector(InputInterface $input): ?EmployerSelector
    {
        $reference = CommandOptions::text($input, self::OPTION_EMPLOYER);
        $cesuNumber = CommandOptions::text($input, self::OPTION_CESU_NUMBER);
        $accountId = CommandOptions::text($input, self::OPTION_ACCOUNT_ID);
        $given = \count(array_filter([$reference, $cesuNumber, $accountId], static fn(?string $value): bool => $value !== null));
        if ($given > 1) {
            throw self::employerSelectorRequired();
        }

        return match (true) {
            $reference !== null => EmployerSelector::byExternalReference(ExternalReference::fromString($reference)),
            $cesuNumber !== null => EmployerSelector::byCesuNumber(CesuNumber::fromString($cesuNumber)),
            $accountId !== null => EmployerSelector::byAccountId(EmployerAccountId::fromString($accountId)),
            default => null,
        };
    }

    /**
     * Exactly one of --declaration and --record-id.
     *
     * @throws InvalidOptionException when none or both are given
     */
    public static function declarationSelector(InputInterface $input): DeclarationSelector
    {
        $reference = CommandOptions::text($input, self::OPTION_DECLARATION);
        $recordId = CommandOptions::text($input, self::OPTION_RECORD_ID);

        return match (true) {
            $reference !== null && $recordId === null => DeclarationSelector::byExternalReference(ExternalReference::fromString($reference)),
            $recordId !== null && $reference === null => DeclarationSelector::byRecordId(DeclarationRecordId::fromString($recordId)),
            default => throw new InvalidOptionException('Give one of --declaration or --record-id.'),
        };
    }

    private static function employerSelectorRequired(): InvalidOptionException
    {
        return new InvalidOptionException('Give one of --employer, --cesu-number or --account-id.');
    }
}
