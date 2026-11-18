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

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;

/**
 * Scripted failures, refusals and lost answers of the simulator.
 *
 * @mago-expect lint:too-many-methods
 */
final class SimulatedCesuGatewayScriptTest extends TestCase
{
    public function testFailuresAreThrownInOrderAndOnlyForTheirOperation(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $first = UrssafUnavailable::withStatus(Operation::DeclareSalary, 503, 30, ApiErrors::none());
        $second = UrssafAuthenticationFailed::withStatus(401);
        $gateway->failNext(Operation::DeclareSalary, $first);
        $gateway->failNext(Operation::DeclareSalary, $second);

        $gateway->estimate(SimulatorSamples::tenant1(), GatewaySamples::estimate());

        static::assertSame($first, SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard())));
        static::assertSame($second, SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard())));
        static::assertSame('SIM-DECL-000001', $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard())->declarationId()->value());
    }

    public function testAFailureIsThrownBeforeAnyStateChange(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->failNext(Operation::DeclareSalary, UrssafUnavailable::withStatus(Operation::DeclareSalary, 503, null, ApiErrors::none()));

        $failure = SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertInstanceOf(UrssafUnavailable::class, $failure);
        static::assertSame([], $gateway->declarationIds());
        static::assertSame([Operation::DeclareSalary], $gateway->calls());
    }

    public function testAFailureWinsOverARefusalAndALostAnswer(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $failure = UrssafAuthenticationFailed::withStatus(401);
        $gateway->failNext(Operation::DeclareSalary, $failure);
        $gateway->refuseNext(Operation::DeclareSalary, 'RIB_ABSENT', 'Absence de RIB', null);
        $gateway->loseNextAnswer(Operation::DeclareSalary);

        $first = SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));
        // The failure consumed neither the refusal nor the lost answer: both apply to the next call (the answer is lost).
        $second = SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertSame($failure, $first);
        static::assertInstanceOf(OutcomeUnknown::class, $second);
        static::assertSame([], $gateway->declarationIds());
    }

    public function testRefusalsAreAnsweredInOrderVerbatim(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->refuseNext(Operation::DeclareSalary, 'RIB_ABSENT', 'Absence de RIB', null);
        $gateway->refuseNext(Operation::DeclareSalary, 'AICI_NON_ACTIVE', "Service d'AICI non activé sur le compte en ligne employeur", null);

        $first = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));
        $second = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertSame(ErrorKind::RibMissing, $first->kind());
        static::assertSame(400, $first->httpStatus());
        static::assertSame(['RIB_ABSENT'], $first->errors()->codes());
        $firstError = $first->errors()->first();
        static::assertNotNull($firstError);
        static::assertSame('Absence de RIB', $firstError->message());
        static::assertNull($firstError->description());
        static::assertSame(ErrorKind::AiciNotActive, $second->kind());
        $secondError = $second->errors()->first();
        static::assertNotNull($secondError);
        static::assertSame("Service d'AICI non activé sur le compte en ligne employeur", $secondError->message());
        static::assertNull($secondError->description());
        static::assertSame([], $gateway->declarationIds());
        static::assertSame('SIM-DECL-000001', $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard())->declarationId()->value());
    }

    public function testARefusalReplacesTheBuiltInRules(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->refuseNext(Operation::VerifyEmployer, 'PARAM_INVALIDE', 'Un des paramètres de votre demande est invalide', null);

        $rejected = SimulatorSamples::rejection(static fn(): EmployerStatus => $gateway->verifyEmployer(
            SimulatorSamples::tenant1(),
            EmployerVerification::employerOnly(SimulatorSamples::employer()),
        ));

        static::assertTrue($rejected->isInvalidParameter());
        static::assertTrue($gateway->verifyEmployer(SimulatorSamples::tenant1(), EmployerVerification::employerOnly(SimulatorSamples::employer()))->registered());
    }

    public function testARefusalWithABusinessErrorUsesTheCatalogueText(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->refuseNextWith(Operation::DeclareSalary, BusinessError::CesuPlusDeadlineExceeded);

        $rejected = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertTrue($rejected->isCesuPlusDeadlineExceeded());
        static::assertSame(ErrorKind::Business, $rejected->kind());
        static::assertSame(['ERR_METIER'], $rejected->errors()->codes());
        static::assertSame('Une erreur métier cesu+ est survenue', $rejected->errors()->first()?->message());
    }

    #[DataProvider('businessErrorMessages')]
    public function testABusinessRefusalUsesTheMessageOfTheSpecification(BusinessError $error, string $message): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->refuseNextWith(Operation::DeclareSalary, $error);

        $rejected = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertSame($message, $rejected->errors()->first()?->message());
    }

    /**
     * The Cesu+ eligibility checks of method 050 carry the "cesu+" message, the other business errors the generic one.
     *
     * @return iterable<string, array{BusinessError, string}>
     */
    public static function businessErrorMessages(): iterable
    {
        foreach ([
            BusinessError::EmployerBankDetailsInactive,
            BusinessError::EmployerOnlineAccountAnomaly,
            BusinessError::EmployerAccountAnomaly,
            BusinessError::CesuPlusDeactivatedByEmployer,
            BusinessError::UnpaidSalary,
            BusinessError::UnpaidContributions,
            BusinessError::EmployeeBankDetailsInactive,
            BusinessError::EmployeeOnlineAccountAnomaly,
            BusinessError::EmployeeAccountAnomaly,
            BusinessError::CesuPlusDeactivated,
            BusinessError::IdenticalBankDetails,
            BusinessError::IdenticalCivilStatus,
            BusinessError::PairAnomaly,
            BusinessError::CesuPlusDeadlineExceeded,
            BusinessError::CesuPlusNetSalaryCeilingExceeded,
        ] as $error) {
            yield $error->value => [$error, 'Une erreur métier cesu+ est survenue'];
        }
        yield 'a business error outside the Cesu+ checks' => [BusinessError::CalculationFailed, ' Une erreur métier est survenue'];
    }

    public function testARefusalWithAnUndocumentedBusinessErrorFails(): void
    {
        $gateway = new SimulatedCesuGateway();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The business error "birth_date_mismatch" is not documented for method 050.');

        $gateway->refuseNextWith(Operation::DeclareSalary, BusinessError::BirthDateMismatch);
    }

    public function testALostAnswerKeepsTheDeclaration(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->loseNextAnswer(Operation::DeclareSalary);

        $failure = SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertInstanceOf(OutcomeUnknown::class, $failure);
        static::assertSame(Operation::DeclareSalary, $failure->operation());
        static::assertSame(504, $failure->httpStatus());
        static::assertCount(1, $gateway->declarationIds());
        static::assertSame('SIM-DECL-000001', $gateway->declarationIds()[0]->value());

        $rejected = SimulatorSamples::rejection(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));
        static::assertTrue($rejected->isDuplicateDeclaration());
    }

    public function testALostAnswerIsConsumedByOneCall(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->loseNextAnswer(Operation::DeclareSalary);
        $gateway->loseNextAnswer(Operation::DeclareSalary);

        $first = SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));
        $second = SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), SimulatorSamples::declarationOfPeriod('2026-09-01', '2026-09-30')));
        $third = $gateway->declare(SimulatorSamples::tenant1(), SimulatorSamples::declarationOfPeriod('2026-08-01', '2026-08-31'));

        static::assertInstanceOf(OutcomeUnknown::class, $first);
        static::assertInstanceOf(OutcomeUnknown::class, $second);
        static::assertSame('SIM-DECL-000003', $third->declarationId()->value());
    }

    public function testALostAnswerIsPerOperation(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->loseNextAnswer(Operation::ActivateAici);

        $result = $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard());

        static::assertSame('SIM-DECL-000001', $result->declarationId()->value());
    }

    public function testALostAnswerOnEmployerRegistrationKeepsTheEmployer(): void
    {
        $gateway = new SimulatedCesuGateway();
        $gateway->loseNextAnswer(Operation::RegisterEmployer);

        $failure = SimulatorSamples::failure(static fn(): CesuNumber => $gateway->registerEmployer(SimulatorSamples::tenant1(), EmployerSamples::newEmployer()));

        static::assertInstanceOf(OutcomeUnknown::class, $failure);
        static::assertSame(Operation::RegisterEmployer, $failure->operation());
        static::assertSame(['SIM00000000001'], array_map(static fn(CesuNumber $cesu): string => $cesu->value(), $gateway->registeredEmployers()));
    }

    public function testALostAnswerSwallowsARefusal(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->loseNextAnswer(Operation::DeclareSalary);
        $gateway->refuseNext(Operation::DeclareSalary, 'RIB_ABSENT', 'Absence de RIB', null);

        $failure = SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertInstanceOf(OutcomeUnknown::class, $failure);
        static::assertSame([], $gateway->declarationIds());
    }

    public function testALostAnswerSwallowsABuiltInRefusal(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->loseNextAnswer(Operation::DeclareSalary);

        $failure = SimulatorSamples::failure(static fn(): DeclarationResult => $gateway->declare(SimulatorSamples::tenant1(), DeclarationSamples::standard()));

        static::assertInstanceOf(OutcomeUnknown::class, $failure);
    }

    public function testALostAnswerOnAReadOperationIsRejected(): void
    {
        $gateway = new SimulatedCesuGateway();

        foreach ([Operation::VerifyEmployer, Operation::VerifyEmployee, Operation::RegisterMandate, Operation::CancelMandate, Operation::EstimateContributions] as $operation) {
            $failure = SimulatorSamples::failure(static fn() => $gateway->loseNextAnswer($operation));
            static::assertInstanceOf(LogicException::class, $failure);
            static::assertSame(
                \sprintf('Only methods 010, 050, 060 and 070 have an unknown outcome; method %s does not.', $operation->methodNumber()),
                $failure->getMessage(),
            );
        }
    }

    public function testEveryCallIsRecordedInOrder(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->failNext(Operation::EstimateContributions, UrssafAuthenticationFailed::withStatus(401));
        $gateway->refuseNext(Operation::VerifyEmployee, 'PARAM_INVALIDE', 'Un des paramètres de votre demande est invalide', null);
        $gateway->loseNextAnswer(Operation::ActivateCesuPlus);

        $failed = SimulatorSamples::failure(static fn(): ContributionsEstimate => $gateway->estimate(SimulatorSamples::tenant1(), GatewaySamples::estimate()));
        $refused = SimulatorSamples::failure(static fn(): EmployeeVerificationResult => $gateway->verifyEmployee(SimulatorSamples::tenant1(), EmployeeSamples::byNir()));
        $lost = SimulatorSamples::failure(static fn(): CesuPlusActivation => $gateway->activateCesuPlus(SimulatorSamples::tenant1(), GatewaySamples::cesuPlusActivation(true)));

        static::assertInstanceOf(UrssafAuthenticationFailed::class, $failed);
        static::assertInstanceOf(UrssafRequestRejected::class, $refused);
        static::assertInstanceOf(OutcomeUnknown::class, $lost);
        static::assertSame(
            [Operation::EstimateContributions, Operation::VerifyEmployee, Operation::ActivateCesuPlus],
            $gateway->calls(),
        );
    }
}
