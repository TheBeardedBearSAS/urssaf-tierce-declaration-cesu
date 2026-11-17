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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class SyncEmployerStatusHandlerTest extends TestCase
{
    public function testRefreshesTheAccountAndItsMandate(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateNotified);
        $harness->mandate(1);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));

        $view = self::handler($harness)(self::command());

        static::assertSame('account-1', $view->accountId);
        static::assertSame('mandate_confirmed', $view->stage);
        static::assertTrue($view->lastStatus?->mandatePresent);
        static::assertSame(Harness::NOW, $view->lastStatus->checkedAt);
        static::assertNull($view->lastStatus->employeeReferenceKind);
        static::assertSame('mandate-1', $view->mandate?->mandateId);
        static::assertSame(MandateState::Confirmed->value, $view->mandate->state);
        static::assertSame(
            ['call:020', 'begin', 'save:mandate-1', 'save:account-1', 'commit', 'publish:EmployerStatusRefreshed', 'publish:MandateConfirmed'],
            $harness->journal->entries(),
        );
        static::assertNull($harness->gateway->verifications()[0]->employee());
    }

    public function testReadsTheStateOfAPairForAnEmployee(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->gateway->willVerifyEmployer(EmployerSamples::statusWithCesuPlus(false, 'actif'));
        $command = new SyncEmployerStatus(
            Harness::tenant(),
            EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1')),
            new EmployeeReferenceDraft(EmployeeSamples::cesuNumber()->value(), null, null),
        );

        $view = self::handler($harness)($command);

        static::assertSame(EmployeeReferenceKind::CesuNumber->value, $view->lastStatus?->employeeReferenceKind);
        static::assertSame(EmployeeSamples::cesuNumber()->value(), $view->lastStatus->employeeCesuNumber);
        static::assertEquals(EmployeeSamples::byCesuNumber(), $harness->gateway->verifications()[0]->employee());
        static::assertNull($view->mandate);
    }

    public function testInvalidEmployeeDraftIsRefusedBeforeAnyCall(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $command = new SyncEmployerStatus(
            Harness::tenant(),
            EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1')),
            new EmployeeReferenceDraft(null, null, null),
        );

        try {
            self::handler($harness)($command);
            static::fail('Expected a validation failure.');
        } catch (ValidationFailed $failure) {
            static::assertNotSame([], $failure->result()->errors());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testUnknownAccountIsRefusedBeforeAnyCall(): void
    {
        $harness = new Harness();

        try {
            self::handler($harness)(self::command());
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('employer_account.not_found', $exception->violationCode());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testFailureOfTheCallPropagatesAndChangesNothing(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $rejection = UrssafRequestRejected::withErrors(Operation::VerifyEmployer, 400, ApiErrors::none(), ErrorKind::InvalidParameter, null);
        $harness->gateway->willVerifyEmployer($rejection);

        try {
            self::handler($harness)(self::command());
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $caught) {
            static::assertSame($rejection, $caught);
        }

        static::assertSame(['call:020'], $harness->journal->entries());
    }

    private static function handler(Harness $harness): SyncEmployerStatusHandler
    {
        return new SyncEmployerStatusHandler(
            new EmployerLocator($harness->accounts),
            new EmployerStatusRefresher($harness->gateway, $harness->mandates, $harness->accounts, $harness->clock, $harness->unitOfWork),
            $harness->mandates,
            $harness->accounts,
        );
    }

    private static function command(): SyncEmployerStatus
    {
        return new SyncEmployerStatus(Harness::tenant(), EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1')), null);
    }
}
