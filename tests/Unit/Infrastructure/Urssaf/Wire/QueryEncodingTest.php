<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\QueryEncoding;

final class QueryEncodingTest extends TestCase
{
    private static function employer(): Employer
    {
        return Employer::of(CesuNumber::fromString('Z0003752390009'), CalendarDate::fromString('1975-01-30'));
    }

    public function testEmployerVerificationWithoutEmployee(): void
    {
        static::assertSame(
            ['dateNaissance' => '1975-01-30'],
            QueryEncoding::employerVerification(EmployerVerification::employerOnly(self::employer())),
        );
    }

    public function testMandateParameters(): void
    {
        static::assertSame(
            ['numeroCesu' => 'Z0003752390009', 'dateNaissance' => '1975-01-30'],
            QueryEncoding::mandate(self::employer()),
        );
    }

    /**
     * @return iterable<string, array{AgreementEncoding, bool, string}>
     */
    public static function agreements(): iterable
    {
        yield 'boolean true' => [AgreementEncoding::Boolean, true, 'true'];
        yield 'boolean false' => [AgreementEncoding::Boolean, false, 'false'];
        yield 'numeric true' => [AgreementEncoding::Numeric, true, '1'];
        yield 'numeric false' => [AgreementEncoding::Numeric, false, '0'];
    }

    #[DataProvider('agreements')]
    public function testCesuPlusActivationParameters(AgreementEncoding $encoding, bool $agreement, string $expected): void
    {
        $request = CesuPlusActivationRequest::of(self::employer(), CesuNumber::fromString('00000123456789'), $agreement);

        static::assertSame(
            ['dateNaissance' => '1975-01-30', 'numeroCesuSalarie' => '00000123456789', 'accord' => $expected],
            QueryEncoding::cesuPlusActivation($request, $encoding),
        );
    }
}
