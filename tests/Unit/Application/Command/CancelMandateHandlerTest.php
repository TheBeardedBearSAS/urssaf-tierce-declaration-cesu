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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\CancelMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\CancelMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
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

final class CancelMandateHandlerTest extends TestCase
{
    public function testCancelsTheNotifiedMandate(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateNotified);
        $harness->mandate(1);
        $harness->gateway->willCancelMandate();

        $view = self::handler($harness)(self::command());

        static::assertSame(
            ['call:031', 'begin', 'save:mandate-1', 'save:account-1', 'commit', 'publish:MandateCancelled', 'publish:EmployerStageChanged'],
            $harness->journal->entries(),
        );
        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame('cancelled', $view->state);
        static::assertSame(Harness::NOW, $view->cancelledAt);
        static::assertEquals([Harness::employer(1)], $harness->gateway->employers());
        static::assertSame([Harness::TENANT], $harness->gateway->tenants());
        static::assertSame(MandateState::Cancelled, $harness->mandates->get(MandateId::fromString('mandate-1'))->state());
        static::assertSame(EmployerStage::MandateCancelled, $harness->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
    }

    public function testCancelsTheConfirmedMandate(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateConfirmed);
        $harness->mandate(1, MandateState::Confirmed);
        $harness->gateway->willCancelMandate();

        static::assertSame('cancelled', self::handler($harness)(self::command())->state);
    }

    /**
     * @return iterable<string, array{?MandateState, string}>
     */
    public static function provideWithoutActiveMandate(): iterable
    {
        yield 'no mandate' => [null, Harness::TENANT];
        yield 'cancelled mandate' => [MandateState::Cancelled, Harness::TENANT];
        yield 'mandate of another tenant' => [MandateState::Notified, Harness::OTHER_TENANT];
    }

    #[DataProvider('provideWithoutActiveMandate')]
    public function testWithoutActiveMandateTheCommandIsRefusedBeforeAnyCall(?MandateState $state, string $tenant): void
    {
        $harness = new Harness();
        $harness->account(1);
        if ($state instanceof MandateState) {
            $harness->mandate(1, $state, $tenant);
        }

        try {
            self::handler($harness)(self::command());
            static::fail('Expected MandateRequired.');
        } catch (MandateRequired $exception) {
            static::assertSame('mandate.required', $exception->violationCode());
            static::assertSame(['cesu_number' => Harness::cesuNumber(1)->value(), 'operation' => '031'], $exception->params());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testUnknownAccountIsRefused(): void
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

    public function testSelectionByCesuNumber(): void
    {
        $harness = new Harness();
        $harness->account(2);
        $harness->mandate(2);
        $harness->gateway->willCancelMandate();

        $view = self::handler($harness)(new CancelMandate(Harness::tenant(), EmployerSelector::byCesuNumber(Harness::cesuNumber(2))));

        static::assertSame('mandate-2', $view->mandateId);
    }

    #[DataProvider('provideCallFailures')]
    public function testEveryFailureOfTheCallPropagatesAndStoresNothing(Throwable $failure): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateNotified);
        $harness->mandate(1);
        $harness->gateway->willCancelMandate($failure);

        try {
            self::handler($harness)(self::command());
            static::fail('Expected a failure.');
        } catch (Throwable $caught) {
            static::assertSame($failure, $caught);
        }

        static::assertSame(['call:031'], $harness->journal->entries());
        static::assertSame(MandateState::Notified, $harness->mandates->get(MandateId::fromString('mandate-1'))->state());
        static::assertSame(EmployerStage::MandateNotified, $harness->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function provideCallFailures(): iterable
    {
        yield 'account unknown' => [UrssafRequestRejected::withErrors(Operation::CancelMandate, 400, ApiErrors::none(), ErrorKind::AccountUnknown, null)];
        yield 'invalid credentials' => [UrssafAuthenticationFailed::withStatus(401)];
        yield 'unavailable' => [UrssafUnavailable::withStatus(Operation::CancelMandate, 503, null, ApiErrors::none())];
        yield 'rate limited' => [RateLimitExceeded::forOperation(Operation::CancelMandate, 2)];
    }

    private static function handler(Harness $harness): CancelMandateHandler
    {
        return new CancelMandateHandler(
            new EmployerLocator($harness->accounts),
            $harness->mandates,
            $harness->accounts,
            $harness->gateway,
            $harness->clock,
            $harness->unitOfWork,
        );
    }

    private static function command(): CancelMandate
    {
        return new CancelMandate(Harness::tenant(), EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1')));
    }
}
