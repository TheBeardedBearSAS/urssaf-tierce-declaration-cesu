<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class PortDtosTest extends TestCase
{
    public function testEmployerVerificationOfAnEmployerOnly(): void
    {
        $employer = EmployerSamples::employer();

        $verification = EmployerVerification::employerOnly($employer);

        static::assertSame($employer, $verification->employer());
        static::assertNull($verification->employee());
    }

    public function testEmployerVerificationOfAPair(): void
    {
        $employer = EmployerSamples::employer();
        $employee = EmployeeByCesuNumber::of(CesuNumber::fromString('00000123456789'));

        $verification = EmployerVerification::forPair($employer, $employee);

        static::assertSame($employer, $verification->employer());
        static::assertSame($employee, $verification->employee());
        static::assertSame(EmployeeReferenceKind::CesuNumber, $employee->kind());
    }

    public function testEmployeeVerificationResult(): void
    {
        $cesuNumber = CesuNumber::fromString('00000123456789');

        $registered = EmployeeVerificationResult::of(true, $cesuNumber);
        $unknown = EmployeeVerificationResult::of(false, null);

        static::assertTrue($registered->registered());
        static::assertSame($cesuNumber, $registered->cesuNumber());
        static::assertFalse($unknown->registered());
        static::assertNull($unknown->cesuNumber());
    }

    public function testEmployeeVerificationResultEquality(): void
    {
        $result = EmployeeVerificationResult::of(true, CesuNumber::fromString('00000123456789'));

        static::assertTrue($result->equals(EmployeeVerificationResult::of(true, CesuNumber::fromString('00000123456789'))));
        static::assertFalse($result->equals(EmployeeVerificationResult::of(false, CesuNumber::fromString('00000123456789'))));
        static::assertFalse($result->equals(EmployeeVerificationResult::of(true, CesuNumber::fromString('00000123456780'))));
        static::assertFalse($result->equals(EmployeeVerificationResult::of(true, null)));
        static::assertFalse(EmployeeVerificationResult::of(true, null)->equals($result));
        static::assertTrue(EmployeeVerificationResult::of(false, null)->equals(EmployeeVerificationResult::of(false, null)));
    }

    public function testCesuPlusActivationRequest(): void
    {
        $employer = EmployerSamples::employer();
        $employeeCesuNumber = CesuNumber::fromString('00000123456789');

        $agreed = CesuPlusActivationRequest::of($employer, $employeeCesuNumber, true);
        $refused = CesuPlusActivationRequest::of($employer, $employeeCesuNumber, false);

        static::assertSame($employer, $agreed->employer());
        static::assertSame($employeeCesuNumber, $agreed->employeeCesuNumber());
        static::assertTrue($agreed->employeeAgreement());
        static::assertFalse($refused->employeeAgreement());
    }

    public function testActivations(): void
    {
        static::assertTrue(CesuPlusActivation::of(true)->isActive());
        static::assertFalse(CesuPlusActivation::of(false)->isActive());
        static::assertTrue(AiciActivation::of(true)->isActive());
        static::assertFalse(AiciActivation::of(false)->isActive());
    }
}
