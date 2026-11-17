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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetDeclarationHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;

final class GetDeclarationHandlerTest extends TestCase
{
    public function testReturnsTheViewOfTheDesignatedRecord(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Declared);

        $view = $this->handler($harness)(new GetDeclaration(
            Harness::tenant(),
            DeclarationSelector::byExternalReference(ExternalReference::fromString('declaration-1')),
        ));

        static::assertSame('record-1', $view->recordId);
        static::assertSame('declared', $view->status->state);
        static::assertSame(Harness::cesuNumber(1)->value(), $view->snapshot->employerCesuNumber);
        static::assertSame([], $harness->journal->entries());
    }

    public function testTheRecordOfAnotherTenantIsNotFound(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Declared, 1, Harness::OTHER_TENANT);

        $this->expectException(RecordNotFound::class);

        $this->handler($harness)(new GetDeclaration(
            Harness::tenant(),
            DeclarationSelector::byExternalReference(ExternalReference::fromString('declaration-1')),
        ));
    }

    private function handler(Harness $harness): GetDeclarationHandler
    {
        return new GetDeclarationHandler(new DeclarationLocator($harness->declarations));
    }
}
