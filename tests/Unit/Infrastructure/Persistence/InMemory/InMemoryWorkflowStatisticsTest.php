<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\StateCount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\ContractSamples;

/**
 * The order and the exact shape of the counts of the in-memory statistics (the contract only compares them sorted).
 */
final class InMemoryWorkflowStatisticsTest extends TestCase
{
    public function testTheCountsKeepTheOrderOfFirstAppearancePerTenantAndState(): void
    {
        $accounts = new InMemoryEmployerAccountRepository();
        $mandates = new InMemoryMandateRepository();
        $declarations = new InMemoryDeclarationRecordRepository();
        $accounts->save(ContractSamples::account(1, EmployerStage::Registered, 'tenant-b'));
        $accounts->save(ContractSamples::account(2, EmployerStage::Registered, 'tenant-a'));
        $accounts->save(ContractSamples::account(3, EmployerStage::MandateNotified, 'tenant-b'));
        $accounts->save(ContractSamples::account(4, EmployerStage::Registered, 'tenant-b'));
        $mandates->save(ContractSamples::mandate(1, MandateState::Confirmed));
        $declarations->save(ContractSamples::record(1, DeclarationState::Pending));
        $statistics = new InMemoryWorkflowStatistics($accounts, $mandates, $declarations);

        static::assertSame(
            [['tenant-b', 'registered', 2], ['tenant-b', 'mandate_notified', 1], ['tenant-a', 'registered', 1]],
            self::flat($statistics->employerStages()),
        );
        static::assertSame([['tenant-1', 'confirmed', 1]], self::flat($statistics->mandateStates()));
        static::assertSame([['tenant-1', 'pending', 1]], self::flat($statistics->declarationStates()));
    }

    /**
     * @param list<StateCount> $counts
     *
     * @return list<array{string, string, int}>
     */
    private static function flat(array $counts): array
    {
        return array_map(
            static fn(StateCount $count): array => [$count->tenantId->value(), $count->state, $count->count],
            $counts,
        );
    }
}
