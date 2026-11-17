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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use Throwable;

final class RegisterMandateHandlerTest extends TestCase
{
    public function testNotifiesTheMandateThenStoresIt(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->gateway->willRegisterMandate();

        $view = self::handler($harness)(self::command());

        static::assertSame(
            ['call:030', 'begin', 'save:id-1', 'save:account-1', 'commit', 'publish:MandateNotified', 'publish:EmployerStageChanged'],
            $harness->journal->entries(),
        );
        static::assertSame('id-1', $view->mandateId);
        static::assertSame(Harness::TENANT, $view->tenantId);
        static::assertSame('employer-1', $view->externalReference);
        static::assertSame(Harness::cesuNumber(1)->value(), $view->cesuNumber);
        static::assertSame('notified', $view->state);
        static::assertSame(Harness::NOW, $view->notifiedAt);
        static::assertEquals([Harness::employer(1)], $harness->gateway->employers());
        static::assertSame([Harness::TENANT], $harness->gateway->tenants());
        static::assertSame(EmployerStage::MandateNotified, $harness->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
        static::assertSame(MandateState::Notified, $harness->mandates->get(MandateId::fromString('id-1'))->state());
    }

    public function testActiveMandateIsReturnedWithoutCall(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateConfirmed);
        $harness->mandate(1, MandateState::Confirmed);

        $view = self::handler($harness)(self::command());

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame('confirmed', $view->state);
        static::assertSame([], $harness->journal->entries());
    }

    public function testCancelledMandateIsNotifiedAgain(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateCancelled);
        $harness->mandate(1, MandateState::Cancelled);
        $harness->gateway->willRegisterMandate();

        $view = self::handler($harness)(self::command());

        static::assertSame('id-1', $view->mandateId);
        static::assertSame(['call:030', 'begin', 'save:id-1', 'save:account-1', 'commit', 'publish:MandateNotified', 'publish:EmployerStageChanged'], $harness->journal->entries());
    }

    public function testMandateOfAnotherTenantDoesNotCount(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->mandate(1, MandateState::Notified, Harness::OTHER_TENANT);
        $harness->gateway->willRegisterMandate();

        static::assertSame('id-1', self::handler($harness)(self::command())->mandateId);
    }

    public function testUnknownAccountIsRefusedBeforeAnyCall(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::Registered, Harness::OTHER_TENANT);

        try {
            self::handler($harness)(self::command());
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('employer_account.not_found', $exception->violationCode());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testTheMandateCarriesTheAccountExternalReference(): void
    {
        $harness = new Harness();
        $harness->account(2);
        $harness->gateway->willRegisterMandate();
        $command = new RegisterMandate(Harness::tenant(), EmployerSelector::byCesuNumber(Harness::cesuNumber(2)));

        $view = self::handler($harness)($command);

        static::assertSame('employer-2', $view->externalReference);
        static::assertSame(Harness::cesuNumber(2)->value(), $view->cesuNumber);
    }

    #[DataProvider('provideCallFailures')]
    public function testEveryFailureOfTheCallPropagatesAndStoresNothing(Throwable $failure): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->gateway->willRegisterMandate($failure);

        try {
            self::handler($harness)(self::command());
            static::fail('Expected a failure.');
        } catch (Throwable $caught) {
            static::assertSame($failure, $caught);
        }

        static::assertSame(['call:030'], $harness->journal->entries());
        static::assertSame([], $harness->mandates->inner()->all());
        static::assertSame(EmployerStage::Registered, $harness->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function provideCallFailures(): iterable
    {
        yield 'held elsewhere' => [UrssafRequestRejected::withErrors(Operation::RegisterMandate, 400, ApiErrors::none(), ErrorKind::MandateHeldElsewhere, null)];
        yield 'account unknown' => [UrssafRequestRejected::withErrors(Operation::RegisterMandate, 400, ApiErrors::none(), ErrorKind::AccountUnknown, null)];
        yield 'invalid credentials' => [UrssafAuthenticationFailed::withStatus(403)];
        yield 'unavailable' => [UrssafUnavailable::withStatus(Operation::RegisterMandate, 429, 3, ApiErrors::none())];
        yield 'rate limited' => [RateLimitExceeded::forOperation(Operation::RegisterMandate, 2)];
    }

    private static function handler(Harness $harness): RegisterMandateHandler
    {
        return new RegisterMandateHandler(
            new EmployerLocator($harness->accounts),
            $harness->mandates,
            $harness->accounts,
            $harness->gateway,
            $harness->ids,
            $harness->clock,
            $harness->unitOfWork,
        );
    }

    private static function command(): RegisterMandate
    {
        return new RegisterMandate(Harness::tenant(), EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1')));
    }
}
