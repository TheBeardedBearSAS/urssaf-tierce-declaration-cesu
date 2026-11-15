<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployeeReferenceParts;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\RequestSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\UnsupportedEmployeeReference;

final class EmployeeReferencePartsTest extends TestCase
{
    public function testByCesuNumberCarriesOnlyTheCesuNumber(): void
    {
        $cesuNumber = CesuNumber::fromString('00000123456789');
        $parts = EmployeeReferenceParts::of(EmployeeByCesuNumber::of($cesuNumber));

        static::assertSame($cesuNumber, $parts->cesuNumber());
        static::assertNull($parts->nir());
        static::assertNull($parts->identity());
    }

    public function testByNirCarriesTheNirAndItsIdentity(): void
    {
        $nir = Nir::fromString('269054958815780');
        $identity = RequestSamples::fullIdentity();
        $parts = EmployeeReferenceParts::of(EmployeeByNir::of($nir, $identity));

        static::assertNull($parts->cesuNumber());
        static::assertSame($nir, $parts->nir());
        static::assertSame($identity, $parts->identity());
    }

    public function testByNirWithoutIdentityCarriesNoIdentity(): void
    {
        $parts = EmployeeReferenceParts::of(EmployeeByNir::of(Nir::fromString('269054958815780'), null));

        static::assertNull($parts->cesuNumber());
        static::assertNotNull($parts->nir());
        static::assertNull($parts->identity());
    }

    public function testByIdentityCarriesOnlyTheIdentity(): void
    {
        $identity = RequestSamples::fullIdentity();
        $parts = EmployeeReferenceParts::of(EmployeeByIdentity::of($identity));

        static::assertNull($parts->cesuNumber());
        static::assertNull($parts->nir());
        static::assertSame($identity, $parts->identity());
    }

    public function testAnUnknownImplementationIsRefused(): void
    {
        try {
            EmployeeReferenceParts::of(new UnsupportedEmployeeReference());
            static::fail('Expected an InvariantViolation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('wire.unsupported_employee_reference', $exception->violationCode());
            static::assertSame('The employee reference has no wire encoding.', $exception->getMessage());
        }
    }
}
