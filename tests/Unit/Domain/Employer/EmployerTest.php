<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

final class EmployerTest extends TestCase
{
    public function testAccessors(): void
    {
        $employer = EmployerSamples::employer();

        static::assertSame('Z0003752390009', $employer->cesuNumber()->value());
        static::assertSame('1975-01-30', $employer->birthDate()->toString());
    }

    public function testEquality(): void
    {
        $employer = EmployerSamples::employer();

        static::assertTrue($employer->equals(EmployerSamples::employer()));
        static::assertFalse($employer->equals(Employer::of(CesuNumber::fromString('Z0003752390010'), CalendarDate::fromString('1975-01-30'))));
        static::assertFalse($employer->equals(Employer::of(CesuNumber::fromString('Z0003752390009'), CalendarDate::fromString('1975-01-31'))));
    }

    public function testBirthDateIsMaskedInDebugOutput(): void
    {
        $employer = EmployerSamples::employer();
        $debug = $employer->__debugInfo();

        static::assertSame(['cesuNumber' => $employer->cesuNumber(), 'birthDate' => '***'], $debug);
        static::assertStringNotContainsString('1975', print_r($employer, true));
    }
}
