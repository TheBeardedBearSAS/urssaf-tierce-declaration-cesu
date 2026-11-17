<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RequeueAfterInvalidCredentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\RequeueReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service\DeclarationPolicies;

final class RequeueAfterInvalidCredentialsHandlerTest extends TestCase
{
    public function testTheDeclarationsRefusedForCredentialsGoBackToPendingInOneTransaction(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::InvalidCredentials);
        $harness->record(2, DeclarationState::InvalidCredentials);
        $harness->record(3, DeclarationState::Declared);
        $harness->record(4, DeclarationState::InvalidCredentials, 1, Harness::OTHER_TENANT);

        $report = $this->requeue($harness, null);

        static::assertSame(2, $report->declarations);
        foreach (['record-1', 'record-2'] as $id) {
            $record = $harness->declarations->inner()->get(DeclarationRecordId::fromString($id));
            static::assertSame(DeclarationState::Pending, $record->state());
            static::assertSame('2026-11-15T10:00:00Z', $record->updatedAt()->toIso8601());
        }
        static::assertSame(
            DeclarationState::Declared,
            $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-3'))->state(),
        );
        static::assertSame(
            DeclarationState::InvalidCredentials,
            $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-4'))->state(),
        );
        static::assertSame(
            ['begin', 'save:record-1', 'save:record-2', 'commit', 'publish:DeclarationRequeued', 'publish:DeclarationRequeued'],
            $harness->journal->entries(),
        );
        static::assertSame([], $harness->gateway->calls());
    }

    public function testNothingToRequeue(): void
    {
        $harness = new Harness();

        static::assertSame(0, $this->requeue($harness, null)->declarations);
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $harness = new Harness(DeclarationPolicies::pageSizes(1, 2));
        $harness->record(1, DeclarationState::InvalidCredentials);
        $harness->record(2, DeclarationState::InvalidCredentials);
        $harness->record(3, DeclarationState::InvalidCredentials);

        static::assertSame(1, $this->requeue($harness, null)->declarations);
        static::assertSame(2, $this->requeue($harness, 10)->declarations);
    }

    public function testTheMaximumNumberOfAttemptsDoesNotStopTheRequeue(): void
    {
        $harness = new Harness(DeclarationPolicies::maxAttempts(1));
        $harness->record(1, DeclarationState::InvalidCredentials, 3);

        static::assertSame(1, $this->requeue($harness, null)->declarations);
    }

    private function requeue(Harness $harness, ?int $limit): RequeueReport
    {
        return (new RequeueAfterInvalidCredentialsHandler($harness->declarations, $harness->clock, $harness->unitOfWork, $harness->policy))(
            new RequeueAfterInvalidCredentials(Harness::tenant(), $limit),
        );
    }
}
