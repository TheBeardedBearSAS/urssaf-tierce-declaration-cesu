<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;

/**
 * Methods 040 and 050 of the simulator.
 *
 * @mago-expect lint:too-many-methods
 */
final class SimulatedCesuGatewayDeclarationTest extends TestCase
{
    public function testTheDefaultEstimateIsTheDeclareFixtureContributions(): void
    {
        $gateway = new SimulatedCesuGateway();

        $estimate = $gateway->estimate(SimulatorSamples::tenant1(), GatewaySamples::estimate());

        static::assertSame(6310, $estimate->employerContributions()->cents());
        static::assertSame(2009, $estimate->employeeContributions()->cents());
        static::assertSame([Operation::EstimateContributions], $gateway->calls());
    }

    public function testTheEstimateCanBeChanged(): void
    {
        $gateway = new SimulatedCesuGateway();
        $changed = ContributionsEstimate::of(Contributions::of(Money::fromCents(100), Money::fromCents(50)));
        $gateway->setEstimate($changed);

        $estimate = $gateway->estimate(SimulatorSamples::tenant1(), GatewaySamples::estimate());

        static::assertSame($changed, $estimate);
    }

    public function testADeclarationWithoutMandateIsRefused(): void
    {
        $gateway = SimulatorSamples::gateway();

        $rejected = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertTrue($rejected->isMandateMissing());
        static::assertTrue($rejected->hasErrorCode('MANDAT_INEXISTANT'));
        static::assertSame(ErrorKind::MandateMissing, $rejected->kind());
        static::assertSame(Operation::DeclareSalary, $rejected->operation());
        $error = $rejected->errors()->first();
        static::assertNotNull($error);
        static::assertSame('Mandat de tierce déclaration absent', $error->message());
        static::assertNull($error->description());
        static::assertSame([], $gateway->declarationIds());
    }

    public function testADeclarationOfAnUnknownEmployerIsRefused(): void
    {
        $gateway = new SimulatedCesuGateway();

        $rejected = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertTrue($rejected->isMandateMissing());
    }

    public function testADeclarationOfAnotherTenantIsRefused(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $rejected = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant2(), DeclarationSamples::standard()));

        static::assertTrue($rejected->isMandateMissing());
    }

    public function testADeclarationIsAcceptedWithTheDefaultAmounts(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $result = $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard());

        static::assertSame('SIM-DECL-000001', $result->declarationId()->value());
        $contributions = $result->contributions();
        static::assertNotNull($contributions);
        static::assertSame(6310, $contributions->employerContributions()->cents());
        static::assertSame(2009, $contributions->employeeContributions()->cents());
        static::assertNull($result->withholdingTax());
        static::assertNull($result->employerContributionsDebit());
        static::assertNull($result->employerWithholdingTaxDebit());
        static::assertNull($result->employerSalaryDebit());
        static::assertNull($result->grossSalary());
        static::assertNull($result->netTaxableSalary());
        static::assertNull($result->salaryTransfer());
        static::assertNull($result->aiciAmount());
        static::assertEquals([$result->declarationId()], $gateway->declarationIds());
    }

    public function testTheSameDeclarationAgainIsADuplicate(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard());

        $rejected = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertTrue($rejected->isDuplicateDeclaration());
        static::assertSame(Operation::DeclareSalary, $rejected->operation());
        static::assertCount(1, $gateway->declarationIds());
    }

    public function testAnotherPeriodIsNotADuplicate(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard());

        $result = $gateway->declare(SimulatorSamples::tenant1(), SimulatorSamples::declarationOfPeriod('2026-09-01', '2026-09-30'));

        static::assertSame('SIM-DECL-000002', $result->declarationId()->value());
    }

    public function testAnotherEmployeeIsNotADuplicate(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard());

        $result = $gateway->declare(SimulatorSamples::tenant1(), SimulatorSamples::declarationOfOtherEmployee());

        static::assertSame('SIM-DECL-000002', $result->declarationId()->value());
    }

    public function testAnotherEmployerIsNotADuplicate(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard());
        $other = CesuNumber::fromString('Z0000000000001');
        $gateway->knowEmployer(SimulatorSamples::unknownEmployer());
        $gateway->mandate($other, SimulatorSamples::tenant1());

        $result = $gateway->declare(SimulatorSamples::tenant1(), SimulatorSamples::declarationOfEmployer($other));

        static::assertSame('SIM-DECL-000002', $result->declarationId()->value());
    }

    public function testTheDuplicateRuleIsGlobalAcrossTenants(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard());
        $gateway->mandate(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant2());

        $rejected = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant2(), DeclarationSamples::standard()));

        static::assertTrue($rejected->isDuplicateDeclaration());
    }

    public function testTheMandateIsCheckedBeforeTheDuplicateRule(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard());
        $gateway->withdrawMandate(SimulatorSamples::cesuNumber());

        $rejected = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertTrue($rejected->isMandateMissing());
    }

    public function testTheDeclarationResultTemplateIsCopiedExceptItsId(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $template = DeclarationSamples::result();
        $gateway->setDeclarationResult($template);

        $result = $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard());

        static::assertSame('SIM-DECL-000001', $result->declarationId()->value());
        static::assertEquals(self::withId($template, $result->declarationId()), $result);
    }

    private static function withId(DeclarationResult $template, DeclarationId $id): DeclarationResult
    {
        return new DeclarationResult(
            $id,
            $template->contributions(),
            $template->withholdingTax(),
            $template->employerContributionsDebit(),
            $template->employerWithholdingTaxDebit(),
            $template->employerSalaryDebit(),
            $template->grossSalary(),
            $template->netTaxableSalary(),
            $template->salaryTransfer(),
            $template->aiciAmount(),
        );
    }
}
