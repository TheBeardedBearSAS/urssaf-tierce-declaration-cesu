<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\FindDeclarations;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\FindDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service\DeclarationPolicies;

final class FindDeclarationsHandlerTest extends TestCase
{
    public function testByExternalReferencesKeepsTheOrderOfTheQueryAndSkipsUnknownOnes(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->record(2, DeclarationState::Declared);

        $views = $this->find($harness, FindDeclarations::byExternalReferences(
            Harness::tenant(),
            ExternalReference::fromString('declaration-2'),
            ExternalReference::fromString('declaration-9'),
            ExternalReference::fromString('declaration-1'),
        ));

        static::assertSame(['record-2', 'record-1'], self::ids($views));
    }

    public function testByRecordIdsKeepsTheOrderAndSkipsUnknownOnesAndOtherTenants(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->record(2, DeclarationState::Declared);
        $harness->record(3, DeclarationState::Declared, 1, Harness::OTHER_TENANT);

        $views = $this->find($harness, FindDeclarations::byRecordIds(
            Harness::tenant(),
            DeclarationRecordId::fromString('record-3'),
            DeclarationRecordId::fromString('record-2'),
            DeclarationRecordId::fromString('record-9'),
            DeclarationRecordId::fromString('record-1'),
        ));

        static::assertSame(['record-2', 'record-1'], self::ids($views));
    }

    public function testNothingRequestedReturnsNothing(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);

        static::assertSame([], $this->find($harness, FindDeclarations::byRecordIds(Harness::tenant())));
        static::assertSame([], $this->find($harness, FindDeclarations::byExternalReferences(Harness::tenant())));
    }

    public function testInStateListsTheRecordsOfTheTenantLeastRecentlyUpdatedFirst(): void
    {
        $harness = new Harness();
        $second = $harness->record(2, DeclarationState::Error);
        $first = $harness->record(1, DeclarationState::Error);
        $harness->record(3, DeclarationState::Error, 1, Harness::OTHER_TENANT);
        $harness->record(4, DeclarationState::Declared);
        $second->retry(Harness::at(Harness::NOW), 5);
        $harness->declarations->add($second);
        $first->retry(Harness::at('2026-11-15T09:30:00Z'), 5);
        $harness->declarations->add($first);

        $views = $this->find($harness, FindDeclarations::inState(Harness::tenant(), DeclarationState::Pending, null));

        static::assertSame(['record-1', 'record-2'], self::ids($views));
    }

    public function testInStateCapsTheLimitWithThePolicy(): void
    {
        $harness = new Harness(DeclarationPolicies::pageSizes(1, 2));
        $harness->record(1, DeclarationState::Declared);
        $harness->record(2, DeclarationState::Declared);
        $harness->record(3, DeclarationState::Declared);

        static::assertCount(1, $this->find($harness, FindDeclarations::inState(Harness::tenant(), DeclarationState::Declared, null)));
        static::assertCount(2, $this->find($harness, FindDeclarations::inState(Harness::tenant(), DeclarationState::Declared, 50)));
        static::assertCount(2, $this->find($harness, FindDeclarations::inState(Harness::tenant(), DeclarationState::Declared, 2)));
        static::assertCount(1, $this->find($harness, FindDeclarations::inState(Harness::tenant(), DeclarationState::Declared, 1)));
    }

    public function testTheNamedConstructorsFillTheQuery(): void
    {
        $reference = ExternalReference::fromString('declaration-1');
        $id = DeclarationRecordId::fromString('record-1');

        $byReference = FindDeclarations::byExternalReferences(Harness::tenant(), $reference);
        $byId = FindDeclarations::byRecordIds(Harness::tenant(), $id);
        $inState = FindDeclarations::inState(Harness::tenant(), DeclarationState::Error, 7);

        static::assertSame([$reference], $byReference->externalReferences);
        static::assertSame([], $byReference->recordIds);
        static::assertNull($byReference->state);
        static::assertNull($byReference->limit);
        static::assertSame([], $byId->externalReferences);
        static::assertSame([$id], $byId->recordIds);
        static::assertNull($byId->state);
        static::assertSame([], $inState->externalReferences);
        static::assertSame([], $inState->recordIds);
        static::assertSame(DeclarationState::Error, $inState->state);
        static::assertSame(7, $inState->limit);
    }

    public function testTheReferencesAreAlwaysAList(): void
    {
        $reference = ExternalReference::fromString('declaration-1');
        $id = DeclarationRecordId::fromString('record-1');

        $byReference = FindDeclarations::byExternalReferences(Harness::tenant(), ...['key' => $reference]);
        $byId = FindDeclarations::byRecordIds(Harness::tenant(), ...['key' => $id]);

        static::assertSame([0 => $reference], $byReference->externalReferences);
        static::assertSame([0 => $id], $byId->recordIds);
    }

    /**
     * @param list<DeclarationView> $views
     *
     * @return list<string>
     */
    private static function ids(array $views): array
    {
        return array_map(static fn(DeclarationView $view): string => $view->recordId, $views);
    }

    /**
     * @return list<DeclarationView>
     */
    private function find(Harness $harness, FindDeclarations $query): array
    {
        return (new FindDeclarationsHandler($harness->declarations, $harness->policy))($query);
    }
}
