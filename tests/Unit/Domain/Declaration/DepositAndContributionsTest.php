<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Deposit;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

final class DepositAndContributionsTest extends TestCase
{
    public function testDepositKeepsItsAmount(): void
    {
        $deposit = Deposit::fromMoney(Money::fromCents(1));

        static::assertSame(1, $deposit->amount()->cents());
    }

    public function testZeroDepositIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::notPositive('deposit'));

        Deposit::fromMoney(Money::fromCents(0));
    }

    public function testDepositEquality(): void
    {
        static::assertTrue(Deposit::fromMoney(Money::fromCents(5))->equals(Deposit::fromMoney(Money::fromCents(5))));
        static::assertFalse(Deposit::fromMoney(Money::fromCents(5))->equals(Deposit::fromMoney(Money::fromCents(6))));
    }

    public function testContributionsKeepTheirAmounts(): void
    {
        $contributions = Contributions::of(Money::fromCents(6310), Money::fromCents(2009));

        static::assertSame(6310, $contributions->employerContributions()->cents());
        static::assertSame(2009, $contributions->employeeContributions()->cents());
    }

    public function testContributionsEqualityComparesEachAmount(): void
    {
        $contributions = Contributions::of(Money::fromCents(1), Money::fromCents(2));

        static::assertTrue($contributions->equals(Contributions::of(Money::fromCents(1), Money::fromCents(2))));
        static::assertFalse($contributions->equals(Contributions::of(Money::fromCents(9), Money::fromCents(2))));
        static::assertFalse($contributions->equals(Contributions::of(Money::fromCents(1), Money::fromCents(9))));
    }
}
