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

use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateCesuPlus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateCesuPlusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\UnexpectedGatewayFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use Throwable;

/**
 * One method per guard outcome and per failure kind.
 *
 * @mago-expect lint:too-many-methods
 */
final class ActivateCesuPlusHandlerTest extends TestCase
{
    private const ZONE = 'Europe/Paris';

    public function testActivatesWithAnActiveMandate(): void
    {
        $harness = new Harness();
        $harness->mandate(1);
        $harness->gateway->willActivateCesuPlus(CesuPlusActivation::of(true));

        $activation = self::handler($harness)(self::command());

        static::assertTrue($activation->isActive());
        static::assertSame(['call:060'], $harness->journal->entries());
        static::assertEquals([Harness::employer(1)], $harness->gateway->employers());
        static::assertSame([Harness::TENANT], $harness->gateway->tenants());
    }

    public function testTheRequestCarriesEveryField(): void
    {
        $harness = new Harness();
        $harness->mandate(1);
        $activator = new class implements CesuPlusActivator {
            /** @var list<array{TenantId, CesuPlusActivationRequest}> */
            private array $received = [];

            public function activateCesuPlus(TenantId $tenantId, CesuPlusActivationRequest $request): CesuPlusActivation
            {
                $this->received[] = [$tenantId, $request];

                return CesuPlusActivation::of(false);
            }

            /**
             * @return list<array{TenantId, CesuPlusActivationRequest}>
             */
            public function received(): array
            {
                return $this->received;
            }
        };

        $activation = (new ActivateCesuPlusHandler($activator, self::guard($harness)))(self::command(false));

        static::assertFalse($activation->isActive());
        [[$tenantId, $request]] = $activator->received();
        static::assertSame(Harness::TENANT, $tenantId->value());
        static::assertEquals(Harness::employer(1), $request->employer());
        static::assertSame(EmployeeSamples::cesuNumber()->value(), $request->employeeCesuNumber()->value());
        static::assertFalse($request->employeeAgreement());
    }

    public function testWithoutMandateTheCommandIsRefusedBeforeAnyCall(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Cancelled);

        try {
            self::handler($harness)(self::command());
            static::fail('Expected MandateRequired.');
        } catch (MandateRequired $exception) {
            static::assertSame(['cesu_number' => Harness::cesuNumber(1)->value(), 'operation' => '060'], $exception->params());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testTheCommandCanOptOutOfTheGuard(): void
    {
        $harness = new Harness();
        $harness->gateway->willActivateCesuPlus(CesuPlusActivation::of(true));

        $activation = self::handler($harness)(new ActivateCesuPlus(Harness::tenant(), Harness::employer(1), EmployeeSamples::cesuNumber(), true, true));

        static::assertTrue($activation->isActive());
        static::assertSame(['call:060'], $harness->journal->entries());
    }

    public function testThePolicyCanDisableTheGuard(): void
    {
        $harness = new Harness(new WorkflowPolicy(new DateTimeZone(self::ZONE), new DateInterval(WorkflowPolicy::DEFAULT_STALE_SUBMISSION_AFTER), WorkflowPolicy::DEFAULT_MAX_SUBMISSION_ATTEMPTS, false));
        $harness->gateway->willActivateCesuPlus(CesuPlusActivation::of(true));

        static::assertTrue(self::handler($harness)(self::command())->isActive());
    }

    public function testAMandateReportedByTheLastStatusIsEnough(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateConfirmed);
        $harness->gateway->willActivateCesuPlus(CesuPlusActivation::of(true));

        static::assertTrue(self::handler($harness)(self::command())->isActive());
    }

    #[DataProvider('provideCallFailures')]
    public function testEveryFailureOfTheCallPropagates(Throwable $failure): void
    {
        $harness = new Harness();
        $harness->mandate(1);
        $harness->gateway->willActivateCesuPlus($failure);

        try {
            self::handler($harness)(self::command());
            static::fail('Expected a failure.');
        } catch (Throwable $caught) {
            static::assertSame($failure, $caught);
        }

        static::assertSame(['call:060'], $harness->journal->entries());
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function provideCallFailures(): iterable
    {
        yield 'mandate missing' => [UrssafRequestRejected::withErrors(Operation::ActivateCesuPlus, 400, ApiErrors::none(), ErrorKind::MandateMissing, null)];
        yield 'invalid credentials' => [UrssafAuthenticationFailed::withStatus(401)];
        yield 'unavailable' => [UrssafUnavailable::withStatus(Operation::ActivateCesuPlus, 503, null, ApiErrors::none())];
        yield 'rate limited' => [RateLimitExceeded::forOperation(Operation::ActivateCesuPlus, 2)];
        yield 'outcome unknown' => [OutcomeUnknown::afterUnreadableResponse(Operation::ActivateCesuPlus, 200, new UnexpectedGatewayFailure())];
    }

    private static function guard(Harness $harness): MandateGuard
    {
        return new MandateGuard($harness->mandates, $harness->accounts, $harness->policy);
    }

    private static function handler(Harness $harness): ActivateCesuPlusHandler
    {
        return new ActivateCesuPlusHandler($harness->gateway, self::guard($harness));
    }

    private static function command(bool $agreement = true): ActivateCesuPlus
    {
        return new ActivateCesuPlus(Harness::tenant(), Harness::employer(1), EmployeeSamples::cesuNumber(), $agreement);
    }
}
