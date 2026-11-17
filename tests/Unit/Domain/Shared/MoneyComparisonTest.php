<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class MoneyComparisonTest extends TestCase
{
    public function testComparisons(): void
    {
        $ten = Money::fromCents(1000);

        static::assertTrue(Money::fromCents(0)->isZero());
        static::assertFalse(Money::fromCents(1)->isZero());
        static::assertTrue($ten->isGreaterThan(Money::fromCents(999)));
        static::assertFalse($ten->isGreaterThan(Money::fromCents(1000)));
        static::assertFalse($ten->isGreaterThan(Money::fromCents(1001)));
        static::assertTrue($ten->equals(Money::fromCents(1000)));
        static::assertFalse($ten->equals(Money::fromCents(999)));
        static::assertFalse($ten->equals(Money::fromCents(1001)));
    }

    public function testAssertAtMost(): void
    {
        $max = Money::fromDecimalString('999.99');

        static::assertTrue(Money::fromDecimalString('999.98')->assertAtMost($max, 'salary')->equals(Money::fromCents(99_998)));
        $atMax = Money::fromDecimalString('999.99');
        static::assertSame($atMax, $atMax->assertAtMost($max, 'salary'));
    }

    public function testAssertAtMostRejectsGreaterAmounts(): void
    {
        $max = Money::fromDecimalString('999.99');

        $exception = DomainAssertions::assertViolationCode(
            'net_salary.out_of_range',
            static fn(): Money => Money::fromDecimalString('1000.00')->assertAtMost($max, 'net_salary'),
        );

        static::assertSame(['min' => '0.00', 'max' => '999.99'], $exception->params());
        static::assertSame('Invalid net_salary: the value must be between 0.00 and 999.99.', $exception->getMessage());

        $second = DomainAssertions::assertViolationCode(
            'net_salary.out_of_range',
            static fn(): Money => Money::fromCents(99_999 + 1)->assertAtMost($max, 'net_salary'),
        );
        static::assertSame(['min' => '0.00', 'max' => '999.99'], $second->params());
    }
}
