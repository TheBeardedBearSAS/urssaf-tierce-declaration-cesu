<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Testing;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;

/**
 * Methods 010, 020 and 021 of the simulator, and its employer helpers.
 *
 * @mago-expect lint:too-many-methods
 */
final class SimulatedCesuGatewayEmployerTest extends TestCase
{
    public function testRegisteredEmployersGetSequentialCesuNumbers(): void
    {
        $gateway = new SimulatedCesuGateway();

        $first = $gateway->registerEmployer(SimulatorSamples::tenant1(), EmployerSamples::newEmployer());
        $second = $gateway->registerEmployer(SimulatorSamples::tenant2(), EmployerSamples::newEmployer());

        static::assertSame('SIM00000000001', $first->value());
        static::assertSame('SIM00000000002', $second->value());
        static::assertSame([$first, $second], $gateway->registeredEmployers());
        static::assertSame([Operation::RegisterEmployer, Operation::RegisterEmployer], $gateway->calls());
    }

    public function testARegisteredEmployerIsKnownWithTheBirthDateOfTheDraft(): void
    {
        $gateway = new SimulatedCesuGateway();
        $cesu = $gateway->registerEmployer(SimulatorSamples::tenant1(), EmployerSamples::newEmployer());
        $employer = Employer::of($cesu, EmployerSamples::newEmployer()->birthDate());

        $gateway->registerMandate(SimulatorSamples::tenant1(), $employer);

        static::assertTrue($gateway->isMandated($cesu, SimulatorSamples::tenant1()));
    }

    public function testAnUnknownEmployerIsNotRegistered(): void
    {
        $gateway = new SimulatedCesuGateway();

        $status = $gateway->verifyEmployer(SimulatorSamples::tenant1(), EmployerVerification::employerOnly(SimulatorSamples::employer()));

        static::assertFalse($status->registered());
        static::assertNull($status->rawOnlineAccountState());
        static::assertNull($status->rawSepaMandateState());
        static::assertFalse($status->mandatePresent());
        static::assertNull($status->rawCesuPlusState());
        static::assertNull($status->cesuPlusBlockingReason());
        static::assertFalse($status->aiciActive());
    }

    public function testABirthDateThatDiffersIsABusinessRefusal(): void
    {
        $gateway = SimulatorSamples::gateway();

        $rejected = SimulatorSamples::rejection(static fn(): EmployerStatus => $gateway->verifyEmployer(
            SimulatorSamples::tenant1(),
            EmployerVerification::employerOnly(SimulatorSamples::employerBornElsewhen()),
        ));

        static::assertTrue($rejected->is(BusinessError::BirthDateMismatch));
        static::assertSame(ErrorKind::Business, $rejected->kind());
        static::assertSame(400, $rejected->httpStatus());
        static::assertSame(Operation::VerifyEmployer, $rejected->operation());
        static::assertTrue($rejected->hasErrorCode('ERR_METIER'));
        static::assertSame('Erreur métier', $rejected->errors()->first()?->message());
    }

    public function testAKnownEmployerHasActiveStatesAndNoMandateByDefault(): void
    {
        $gateway = SimulatorSamples::gateway();

        $status = $gateway->verifyEmployer(SimulatorSamples::tenant1(), EmployerVerification::employerOnly(SimulatorSamples::employer()));

        static::assertTrue($status->registered());
        static::assertSame('actif', $status->rawOnlineAccountState());
        static::assertSame('actif', $status->rawSepaMandateState());
        static::assertFalse($status->mandatePresent());
        static::assertNull($status->rawCesuPlusState());
        static::assertNull($status->cesuPlusBlockingReason());
        static::assertFalse($status->aiciActive());
        static::assertSame([Operation::VerifyEmployer], $gateway->calls());
    }

    public function testTheAccountStatesAreScriptedRaw(): void
    {
        $gateway = SimulatorSamples::gateway();
        $gateway->setAccountStates(SimulatorSamples::cesuNumber(), 'radie', 'absent');

        $status = $gateway->verifyEmployer(SimulatorSamples::tenant1(), EmployerVerification::employerOnly(SimulatorSamples::employer()));

        static::assertSame('radie', $status->rawOnlineAccountState());
        static::assertSame('absent', $status->rawSepaMandateState());
    }

    public function testTheMandateIsPresentOnlyForItsHolder(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $verification = EmployerVerification::employerOnly(SimulatorSamples::employer());

        static::assertTrue($gateway->verifyEmployer(SimulatorSamples::tenant1(), $verification)->mandatePresent());
        static::assertFalse($gateway->verifyEmployer(SimulatorSamples::tenant2(), $verification)->mandatePresent());
    }

    public function testCesuPlusIsActiveOnlyForAnActivePair(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->activateCesuPlus(SimulatorSamples::tenant1(), GatewaySamples::cesuPlusActivation(true));
        $employee = EmployeeSamples::byCesuNumber();

        $withPair = $gateway->verifyEmployer(SimulatorSamples::tenant1(), EmployerVerification::forPair(SimulatorSamples::employer(), $employee));
        $employerOnly = $gateway->verifyEmployer(SimulatorSamples::tenant1(), EmployerVerification::employerOnly(SimulatorSamples::employer()));
        $otherEmployee = $gateway->verifyEmployer(
            SimulatorSamples::tenant1(),
            EmployerVerification::forPair(SimulatorSamples::employer(), EmployeeByCesuNumber::of(CesuNumber::fromString('00000999999999'))),
        );

        static::assertSame('active', $withPair->rawCesuPlusState());
        static::assertTrue($withPair->isCesuPlusActive());
        static::assertNull($employerOnly->rawCesuPlusState());
        static::assertNull($otherEmployee->rawCesuPlusState());
    }

    public function testAnEmployeeWithoutCesuNumberHasNoCesuPlus(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->activateCesuPlus(SimulatorSamples::tenant1(), GatewaySamples::cesuPlusActivation(true));

        $status = $gateway->verifyEmployer(
            SimulatorSamples::tenant1(),
            EmployerVerification::forPair(SimulatorSamples::employer(), EmployeeSamples::byNir()),
        );

        static::assertNull($status->rawCesuPlusState());
    }

    public function testAiciIsReportedOnceActivated(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();
        $gateway->activateCesuPlus(SimulatorSamples::tenant1(), GatewaySamples::cesuPlusActivation(true));
        $gateway->activateAici(SimulatorSamples::tenant1(), SimulatorSamples::aiciRequest());

        $status = $gateway->verifyEmployer(SimulatorSamples::tenant1(), EmployerVerification::employerOnly(SimulatorSamples::employer()));

        static::assertTrue($status->aiciActive());
    }

    public function testAnEmployeeIsFoundByItsCesuNumber(): void
    {
        $gateway = new SimulatedCesuGateway();
        $gateway->knowEmployee(EmployeeSamples::cesuNumber());

        $result = $gateway->verifyEmployee(SimulatorSamples::tenant1(), EmployeeSamples::byCesuNumber());

        static::assertTrue($result->registered());
        static::assertSame('00000123456789', $result->cesuNumber()?->value());
        static::assertSame([Operation::VerifyEmployee], $gateway->calls());
    }

    public function testAnEmployeeIsFoundByAnAlias(): void
    {
        $gateway = new SimulatedCesuGateway();
        $gateway->knowEmployee(EmployeeSamples::cesuNumber(), EmployeeSamples::byIdentity(), EmployeeSamples::byNir());

        $byNir = $gateway->verifyEmployee(SimulatorSamples::tenant1(), EmployeeSamples::byNir());
        $byIdentity = $gateway->verifyEmployee(SimulatorSamples::tenant1(), EmployeeSamples::byIdentity());

        static::assertTrue($byNir->registered());
        static::assertSame('00000123456789', $byNir->cesuNumber()?->value());
        static::assertTrue($byIdentity->registered());
    }

    public function testAnEmployeeWithAnAllDigitsCesuNumberIsFound(): void
    {
        $gateway = new SimulatedCesuGateway();
        $gateway->knowEmployee(CesuNumber::fromString('12345678901234'), EmployeeSamples::byNir());

        $byNumber = $gateway->verifyEmployee(SimulatorSamples::tenant1(), EmployeeByCesuNumber::of(CesuNumber::fromString('12345678901234')));
        $byAlias = $gateway->verifyEmployee(SimulatorSamples::tenant1(), EmployeeSamples::byNir());

        static::assertTrue($byNumber->registered());
        static::assertSame('12345678901234', $byNumber->cesuNumber()?->value());
        static::assertTrue($byAlias->registered());
        static::assertSame('12345678901234', $byAlias->cesuNumber()?->value());
    }

    public function testAnAliasOfAnotherEmployeeIsNotMatched(): void
    {
        $gateway = new SimulatedCesuGateway();
        $gateway->knowEmployee(EmployeeSamples::cesuNumber(), EmployeeSamples::byIdentity());
        $gateway->knowEmployee(CesuNumber::fromString('00000999999999'), EmployeeSamples::byNir());

        $result = $gateway->verifyEmployee(SimulatorSamples::tenant1(), EmployeeSamples::byNir());

        static::assertSame('00000999999999', $result->cesuNumber()?->value());
    }

    public function testAnUnknownEmployeeIsNotRegistered(): void
    {
        $gateway = new SimulatedCesuGateway();
        $gateway->knowEmployee(EmployeeSamples::cesuNumber(), EmployeeSamples::byIdentity());

        $byNir = $gateway->verifyEmployee(SimulatorSamples::tenant1(), EmployeeSamples::byNir());
        $byCesu = $gateway->verifyEmployee(SimulatorSamples::tenant1(), EmployeeByCesuNumber::of(CesuNumber::fromString('00000999999999')));

        static::assertFalse($byNir->registered());
        static::assertNull($byNir->cesuNumber());
        static::assertFalse($byCesu->registered());
    }

    public function testKnowingAnEmployerTwiceFails(): void
    {
        $gateway = SimulatorSamples::gateway();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The employer "Z0003752390009" is already known to the simulator.');

        $gateway->knowEmployer(SimulatorSamples::employer());
    }

    public function testKnowingAnEmployerAndItsMandateRecordsNoCall(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        static::assertSame([], $gateway->calls());
        static::assertSame([], $gateway->registeredEmployers());
        static::assertSame([], $gateway->declarationIds());
    }
}
