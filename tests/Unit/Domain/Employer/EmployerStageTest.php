<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\RegistrationOrigin;

final class EmployerStageTest extends TestCase
{
    public function testWireValues(): void
    {
        static::assertSame(
            ['registered', 'mandate_notified', 'mandate_confirmed', 'aici_active', 'mandate_cancelled'],
            array_map(static fn(EmployerStage $stage): string => $stage->value, EmployerStage::cases()),
        );
        static::assertSame('registered', RegistrationOrigin::Registered->value);
        static::assertSame('imported', RegistrationOrigin::Imported->value);
    }

    /**
     * @return iterable<string, array{EmployerStage, EmployerStage}>
     */
    public static function provideMandateNotified(): iterable
    {
        yield 'registered' => [EmployerStage::Registered, EmployerStage::MandateNotified];
        yield 'notified' => [EmployerStage::MandateNotified, EmployerStage::MandateNotified];
        yield 'confirmed' => [EmployerStage::MandateConfirmed, EmployerStage::MandateConfirmed];
        yield 'aici' => [EmployerStage::AiciActive, EmployerStage::AiciActive];
        yield 'cancelled' => [EmployerStage::MandateCancelled, EmployerStage::MandateNotified];
    }

    #[DataProvider('provideMandateNotified')]
    public function testAfterMandateNotified(EmployerStage $from, EmployerStage $expected): void
    {
        static::assertSame($expected, $from->afterMandateNotified());
    }

    public function testAfterMandateCancelled(): void
    {
        foreach (EmployerStage::cases() as $stage) {
            static::assertSame(EmployerStage::MandateCancelled, $stage->afterMandateCancelled());
        }
    }

    /**
     * @return iterable<string, array{EmployerStage, bool, bool, EmployerStage}>
     */
    public static function provideAfterStatus(): iterable
    {
        $expectations = [
            'registered' => [EmployerStage::Registered, EmployerStage::Registered, EmployerStage::MandateConfirmed, EmployerStage::AiciActive],
            'notified' => [EmployerStage::MandateNotified, EmployerStage::MandateNotified, EmployerStage::MandateConfirmed, EmployerStage::AiciActive],
            'confirmed' => [EmployerStage::MandateConfirmed, EmployerStage::Registered, EmployerStage::MandateConfirmed, EmployerStage::AiciActive],
            'aici' => [EmployerStage::AiciActive, EmployerStage::Registered, EmployerStage::MandateConfirmed, EmployerStage::AiciActive],
            'cancelled' => [EmployerStage::MandateCancelled, EmployerStage::MandateCancelled, EmployerStage::MandateConfirmed, EmployerStage::AiciActive],
        ];
        foreach ($expectations as $name => [$from, $withoutMandate, $withMandate, $withAici]) {
            yield $name . ', no mandate' => [$from, false, false, $withoutMandate];
            yield $name . ', no mandate, AICI flag ignored' => [$from, false, true, $withoutMandate];
            yield $name . ', mandate' => [$from, true, false, $withMandate];
            yield $name . ', mandate and AICI' => [$from, true, true, $withAici];
        }
    }

    #[DataProvider('provideAfterStatus')]
    public function testAfterStatus(EmployerStage $from, bool $mandatePresent, bool $aiciActive, EmployerStage $expected): void
    {
        static::assertSame($expected, $from->afterStatus(EmployerSamples::status($mandatePresent, $aiciActive)));
    }

    public function testTheCesuPlusStateDoesNotChangeTheStage(): void
    {
        static::assertSame(
            EmployerStage::MandateConfirmed,
            EmployerStage::Registered->afterStatus(EmployerSamples::statusWithCesuPlus(true, 'active')),
        );
    }
}
