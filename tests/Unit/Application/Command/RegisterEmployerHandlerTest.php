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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\UnexpectedGatewayFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use Throwable;

final class RegisterEmployerHandlerTest extends TestCase
{
    public function testRegistersTheEmployerAfterTheCall(): void
    {
        $harness = new Harness();
        $harness->gateway->willRegisterEmployer(Harness::cesuNumber(1));

        $view = self::handler($harness)(self::command());

        static::assertSame(['call:010', 'begin', 'save:id-1', 'commit', 'publish:EmployerRegistered'], $harness->journal->entries());
        static::assertSame('id-1', $view->accountId);
        static::assertSame(Harness::TENANT, $view->tenantId);
        static::assertSame('employer-1', $view->externalReference);
        static::assertSame(Harness::cesuNumber(1)->value(), $view->cesuNumber);
        static::assertSame('registered', $view->origin);
        static::assertSame('registered', $view->stage);
        static::assertNull($view->lastStatus);
        static::assertNull($view->mandate);
        static::assertSame(Harness::NOW, $view->createdAt);
        static::assertSame(Harness::NOW, $view->updatedAt);
        static::assertEquals([EmployerSamples::newEmployer()], $harness->gateway->newEmployers());
        static::assertSame([Harness::TENANT], $harness->gateway->tenants());
        $stored = $harness->accounts->get(EmployerAccountId::fromString('id-1'));
        static::assertSame('1975-01-30', $stored->birthDate()->toString());
        static::assertSame(Harness::cesuNumber(1)->value(), $stored->cesuNumber()->value());
    }

    public function testInvalidDraftIsRefusedBeforeAnyCall(): void
    {
        $harness = new Harness();
        $valid = EmployerSamples::newEmployerDraft();
        $draft = new NewEmployerDraft($valid->civility, '', $valid->firstName, $valid->birthDate, $valid->email, $valid->phone, $valid->address, $valid->bankDetails);

        try {
            self::handler($harness)(new RegisterEmployer(Harness::tenant(), ExternalReference::fromString('employer-1'), $draft));
            static::fail('Expected a validation failure.');
        } catch (ValidationFailed $failure) {
            static::assertSame('lastName', $failure->result()->errors()[0]->path());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testExternalReferenceAlreadyTrackedIsRefusedWithoutCall(): void
    {
        $harness = new Harness();
        $harness->account(1);

        $exception = self::thrown($harness, EmployerAlreadyTracked::class);

        static::assertSame('employer_account.already_tracked', $exception->violationCode());
        static::assertSame(['external_reference' => 'employer-1', 'employer_account_id' => 'account-1'], $exception->params());
        static::assertSame([], $harness->journal->entries());
    }

    public function testTheSameReferenceOfAnotherTenantIsAnotherEmployer(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::Registered, Harness::OTHER_TENANT);
        $harness->gateway->willRegisterEmployer(Harness::cesuNumber(1));

        $view = self::handler($harness)(self::command());

        static::assertSame('id-1', $view->accountId);
    }

    public function testCesuNumberAlreadyTrackedIsRefusedAfterTheCall(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->gateway->willRegisterEmployer(Harness::cesuNumber(1));

        try {
            self::handler($harness)(new RegisterEmployer(Harness::tenant(), ExternalReference::fromString('other'), EmployerSamples::newEmployerDraft()));
            static::fail('Expected a refusal.');
        } catch (EmployerAlreadyTracked $exception) {
            static::assertSame(['cesu_number' => Harness::cesuNumber(1)->value(), 'employer_account_id' => 'account-1'], $exception->params());
        }

        static::assertSame(['call:010'], $harness->journal->entries());
    }

    #[DataProvider('provideCallFailures')]
    public function testEveryFailureOfTheCallPropagatesAndStoresNothing(Throwable $failure): void
    {
        $harness = new Harness();
        $harness->gateway->willRegisterEmployer($failure);

        $caught = self::thrown($harness, $failure::class);

        static::assertSame($failure, $caught);
        static::assertSame(['call:010'], $harness->journal->entries());
        static::assertSame([], $harness->accounts->inner()->all());
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function provideCallFailures(): iterable
    {
        yield 'e-mail already known' => [UrssafRequestRejected::withErrors(Operation::RegisterEmployer, 400, ApiErrors::none(), ErrorKind::Business, BusinessError::EmailAlreadyKnown)];
        yield 'invalid credentials' => [UrssafAuthenticationFailed::withStatus(401)];
        yield 'unavailable' => [UrssafUnavailable::withStatus(Operation::RegisterEmployer, 503, null, ApiErrors::none())];
        yield 'rate limited' => [RateLimitExceeded::forOperation(Operation::RegisterEmployer, 2)];
        yield 'outcome unknown' => [OutcomeUnknown::afterUnreadableResponse(Operation::RegisterEmployer, 200, new UnexpectedGatewayFailure())];
    }

    private static function handler(Harness $harness): RegisterEmployerHandler
    {
        return new RegisterEmployerHandler($harness->accounts, $harness->gateway, $harness->ids, $harness->clock, $harness->unitOfWork);
    }

    private static function command(): RegisterEmployer
    {
        return new RegisterEmployer(Harness::tenant(), ExternalReference::fromString('employer-1'), EmployerSamples::newEmployerDraft());
    }

    /**
     * @template T of Throwable
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function thrown(Harness $harness, string $class): Throwable
    {
        try {
            self::handler($harness)(self::command());
        } catch (Throwable $exception) {
            static::assertInstanceOf($class, $exception);

            return $exception;
        }
        static::fail('Expected an exception.');
    }
}
