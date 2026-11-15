<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Testing;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;

/**
 * Methods 030 and 031 of the simulator, and its mandate helpers.
 *
 * @mago-expect lint:too-many-methods
 */
final class SimulatedCesuGatewayMandateTest extends TestCase
{
    public function testTheMandateIsRegisteredForTheTenant(): void
    {
        $gateway = SimulatorSamples::gateway();

        $gateway->registerMandate(SimulatorSamples::tenant1(), SimulatorSamples::employer());

        static::assertTrue($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
        static::assertFalse($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant2()));
        static::assertSame([Operation::RegisterMandate], $gateway->calls());
    }

    public function testAnUnknownEmployerCannotBeMandated(): void
    {
        $gateway = SimulatorSamples::gateway();

        $rejected = SimulatorSamples::rejection(static fn() => $gateway->registerMandate(SimulatorSamples::tenant1(), SimulatorSamples::unknownEmployer()));

        static::assertTrue($rejected->hasErrorCode('ER_API_020_0000'));
        static::assertTrue($rejected->isAccountUnknown());
        static::assertSame(ErrorKind::AccountUnknown, $rejected->kind());
        static::assertSame(400, $rejected->httpStatus());
        static::assertSame(Operation::RegisterMandate, $rejected->operation());
        $error = $rejected->errors()->first();
        static::assertNotNull($error);
        static::assertSame('Aucun compte CESU trouvé avec ces paramètres', $error->message());
        static::assertSame('Aucun compte CESU trouvé avec ces paramètres', $error->description());
    }

    public function testAWrongBirthDateCannotBeMandated(): void
    {
        $gateway = SimulatorSamples::gateway();

        $rejected = SimulatorSamples::rejection(static fn() => $gateway->registerMandate(SimulatorSamples::tenant1(), SimulatorSamples::employerBornElsewhen()));

        static::assertTrue($rejected->isAccountUnknown());
        static::assertFalse($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
    }

    public function testAMandateHeldByAnotherTenantIsRefused(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $rejected = SimulatorSamples::rejection(static fn() => $gateway->registerMandate(SimulatorSamples::tenant2(), SimulatorSamples::employer()));

        static::assertTrue($rejected->isMandateHeldElsewhere());
        static::assertSame(ErrorKind::MandateHeldElsewhere, $rejected->kind());
        static::assertTrue($rejected->hasErrorCode('COMPTE_DEJA_MANDATE'));
        $error = $rejected->errors()->first();
        static::assertNotNull($error);
        static::assertSame('Le compte est déjà mandaté', $error->message());
        static::assertSame('Il existe déjà un autre établissement mandataire pour ce numéro de compte', $error->description());
        static::assertTrue($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
    }

    public function testRegisteringTheMandateAgainIsAccepted(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $gateway->registerMandate(SimulatorSamples::tenant1(), SimulatorSamples::employer());

        static::assertTrue($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
    }

    public function testTheHolderCancelsTheMandate(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $gateway->cancelMandate(SimulatorSamples::tenant1(), SimulatorSamples::employer());

        static::assertFalse($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
        static::assertSame([Operation::CancelMandate], $gateway->calls());
        $gateway->registerMandate(SimulatorSamples::tenant2(), SimulatorSamples::employer());
        static::assertTrue($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant2()));
    }

    public function testCancellingWithoutMandateIsAccepted(): void
    {
        $gateway = SimulatorSamples::gateway();

        $gateway->cancelMandate(SimulatorSamples::tenant1(), SimulatorSamples::employer());

        static::assertFalse($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
    }

    public function testAnotherTenantCannotCancelTheMandate(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $rejected = SimulatorSamples::rejection(static fn() => $gateway->cancelMandate(SimulatorSamples::tenant2(), SimulatorSamples::employer()));

        static::assertTrue($rejected->isMandateHeldElsewhere());
        static::assertSame(Operation::CancelMandate, $rejected->operation());
        static::assertTrue($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
    }

    public function testCancellingAnUnknownEmployerIsRefused(): void
    {
        $gateway = SimulatorSamples::gateway();

        $rejected = SimulatorSamples::rejection(static fn() => $gateway->cancelMandate(SimulatorSamples::tenant1(), SimulatorSamples::unknownEmployer()));

        static::assertTrue($rejected->isAccountUnknown());
        static::assertSame(Operation::CancelMandate, $rejected->operation());
    }

    public function testAMandateObtainedOutsideThePackageReplacesTheHolder(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $gateway->mandate(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant2());

        static::assertFalse($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
        static::assertTrue($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant2()));
        static::assertSame([], $gateway->calls());
    }

    public function testAWithdrawnMandateIsGone(): void
    {
        $gateway = SimulatorSamples::mandatedGateway();

        $gateway->withdrawMandate(SimulatorSamples::cesuNumber());

        static::assertFalse($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
    }

    public function testTheHelpersRefuseAnUnknownEmployer(): void
    {
        $gateway = new SimulatedCesuGateway();
        $cesu = SimulatorSamples::cesuNumber();
        $message = 'The employer "Z0003752390009" is not known to the simulator.';

        foreach ([
            static fn() => $gateway->mandate($cesu, SimulatorSamples::tenant1()),
            static fn() => $gateway->withdrawMandate($cesu),
            static fn() => $gateway->setAccountStates($cesu, 'actif', 'actif'),
        ] as $call) {
            $failure = SimulatorSamples::failure($call);
            static::assertInstanceOf(LogicException::class, $failure);
            static::assertSame($message, $failure->getMessage());
        }
    }

    public function testTheInspectionOfAnUnknownEmployerIsNegative(): void
    {
        $gateway = new SimulatedCesuGateway();
        $employee = CesuNumber::fromString('00000123456789');

        static::assertFalse($gateway->isMandated(SimulatorSamples::cesuNumber(), SimulatorSamples::tenant1()));
        static::assertFalse($gateway->isCesuPlusActive(SimulatorSamples::cesuNumber(), $employee));
        static::assertFalse($gateway->isAiciActive(SimulatorSamples::cesuNumber()));
    }
}
