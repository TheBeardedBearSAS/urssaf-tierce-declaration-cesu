<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * Aggregates of the mapping round trips, by case name (see {@see DoctrineMappingTest}): fictitious values only, events
 * released.
 */
final class DoctrineMappingSamples
{
    private function __construct() {}

    /**
     * @param string $case "<state>:check" or "<state>:nocheck" with a {@see MandateState} value
     */
    public static function mandate(string $case): Mandate
    {
        [$state, $check] = explode(':', $case);
        $target = MandateState::from($state);
        $mandate = ContractSamples::pendingMandate(1);
        if ($check === 'check') {
            $mandate->applyStatus($target === MandateState::Confirmed, ContractSamples::at('2026-11-10T11:00:00Z'));
        }
        if ($target === MandateState::Cancelled) {
            $mandate->cancel(ContractSamples::at('2026-11-10T12:00:00Z'));
        }
        $mandate->releaseEvents();

        return $mandate;
    }

    public static function account(string $case): EmployerAccount
    {
        if ($case === 'imported') {
            $account = EmployerAccount::import(
                EmployerAccountId::fromString('account-1'),
                ContractSamples::tenant(),
                ExternalReference::fromString('employer-1'),
                ContractSamples::employer(1),
                ContractSamples::at(),
            );
            $account->releaseEvents();

            return $account;
        }
        if (str_starts_with($case, 'stage:')) {
            return ContractSamples::account(1, EmployerStage::from(substr($case, \strlen('stage:'))));
        }
        $account = ContractSamples::account(1);
        [$status, $employee] = match ($case) {
            'status-employee' => [EmployerSamples::statusWithCesuPlus(true, 'actif'), EmployeeByCesuNumber::of(ContractSamples::cesuNumber(2))],
            'status' => [EmployerSamples::status(true, true), null],
            'status-nulls' => [new EmployerStatus(false, null, null, false, null, null, false), null],
            'status-unknown' => [new EmployerStatus(true, 'inconnu-demain', 'actif', true, 'etat-futur', 'raison-future', false), null],
            default => [null, null],
        };
        if ($status instanceof EmployerStatus) {
            $account->applyStatus($status, $employee, ContractSamples::at('2026-11-10T10:00:00Z'));
        }
        $account->releaseEvents();

        return $account;
    }

    public static function record(string $case): DeclarationRecord
    {
        if (str_starts_with($case, 'state:')) {
            return ContractSamples::record(1, DeclarationState::from(substr($case, \strlen('state:'))), 2);
        }
        $snapshot = match ($case) {
            'cesu-plus' => DeclarationSamples::snapshotOf(DeclarationSamples::cesuPlus()),
            'foster-carer' => DeclarationSamples::snapshotOf(StandardDeclaration::of(DeclarationSamples::contentWithFosterIncome())),
            default => DeclarationSamples::snapshotOf(StandardDeclaration::of(DeclarationSamples::build(null, DeclarationSamples::nirEmployee(), null, null, DeclarationSamples::approval()))),
        };

        return self::recordOf($case, $snapshot);
    }

    private static function recordOf(string $case, DeclarationSnapshot $snapshot): DeclarationRecord
    {
        $record = DeclarationRecord::record(
            DeclarationRecordId::fromString('record-' . ['cesu-plus' => 1, 'foster-carer' => 2, 'nir' => 3][$case]),
            ContractSamples::tenant(),
            ExternalReference::fromString('declaration-' . $case),
            $snapshot,
            ContractSamples::at(),
        );
        $record->releaseEvents();

        return $record;
    }
}
