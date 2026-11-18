<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;

/**
 * One test per rule of the guard (ADR 0009), hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class MandateGuardTest extends TestCase
{
    public function testTheSkipFlagBypassesTheGuard(): void
    {
        $harness = new Harness();

        $this->guard($harness)->assertMandate(Harness::tenant(), Harness::cesuNumber(1), Operation::DeclareSalary, true);

        $this->expectNotToPerformAssertions();
    }

    public function testThePolicyCanSwitchTheGuardOff(): void
    {
        $harness = new Harness(DeclarationPolicies::withoutMandateGuard());

        $this->guard($harness)->assertMandate(Harness::tenant(), Harness::cesuNumber(1), Operation::DeclareSalary, false);

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('provideActiveMandates')]
    public function testAnActiveLocalMandateIsEnough(MandateState $state): void
    {
        $harness = new Harness();
        $harness->mandate(1, $state);

        $this->guard($harness)->assertMandate(Harness::tenant(), Harness::cesuNumber(1), Operation::DeclareSalary, false);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return iterable<string, array{MandateState}>
     */
    public static function provideActiveMandates(): iterable
    {
        yield 'notified' => [MandateState::Notified];
        yield 'confirmed' => [MandateState::Confirmed];
    }

    public function testACancelledMandateIsRefusedEvenWhenTheAccountStageReportsTheMandate(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Cancelled);
        $harness->account(1, EmployerStage::MandateConfirmed);

        $this->assertRefused($harness, Operation::DeclareSalary, '050');
    }

    public function testAnActiveMandateOfAnotherTenantIsIgnored(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed, Harness::OTHER_TENANT);

        $this->assertRefused($harness, Operation::ActivateCesuPlus, '060');
    }

    #[DataProvider('provideStagesReportingTheMandate')]
    public function testWithoutAnyLocalMandateAStageReportingTheMandatePasses(EmployerStage $stage): void
    {
        $harness = new Harness();
        $harness->account(1, $stage);

        $this->guard($harness)->assertMandate(Harness::tenant(), Harness::cesuNumber(1), Operation::DeclareSalary, false);

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('provideStagesNotReportingTheMandate')]
    public function testWithoutAnyLocalMandateOtherStagesAreRefused(EmployerStage $stage): void
    {
        $harness = new Harness();
        $harness->account(1, $stage);

        $this->assertRefused($harness, Operation::DeclareSalary, '050');
    }

    /**
     * @return iterable<string, array{EmployerStage}>
     */
    public static function provideStagesReportingTheMandate(): iterable
    {
        yield 'mandate confirmed' => [EmployerStage::MandateConfirmed];
        yield 'aici active' => [EmployerStage::AiciActive];
    }

    /**
     * @return iterable<string, array{EmployerStage}>
     */
    public static function provideStagesNotReportingTheMandate(): iterable
    {
        yield 'registered' => [EmployerStage::Registered];
        yield 'mandate notified' => [EmployerStage::MandateNotified];
        yield 'mandate cancelled' => [EmployerStage::MandateCancelled];
    }

    public function testAnAccountOfAnotherTenantDoesNotCount(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateConfirmed, Harness::OTHER_TENANT);

        $this->assertRefused($harness, Operation::DeclareSalary, '050');
    }

    public function testAnUnknownEmployerIsRefused(): void
    {
        $this->assertRefused(new Harness(), Operation::ActivateCesuPlus, '060');
    }

    private function assertRefused(Harness $harness, Operation $operation, string $method): void
    {
        try {
            $this->guard($harness)->assertMandate(Harness::tenant(), Harness::cesuNumber(1), $operation, false);
            static::fail('The guard should refuse.');
        } catch (MandateRequired $refusal) {
            static::assertSame('mandate.required', $refusal->violationCode());
            static::assertSame(['cesu_number' => Harness::cesuNumber(1)->value(), 'operation' => $method], $refusal->params());
        }
    }

    private function guard(Harness $harness): MandateGuard
    {
        return new MandateGuard($harness->mandates, $harness->accounts, $harness->policy);
    }
}
