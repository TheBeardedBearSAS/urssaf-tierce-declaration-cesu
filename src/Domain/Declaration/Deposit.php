<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Deposit (`montantAcompte`, api-reference §2.7): strictly positive (Swagger minimum 0.01, §5.4). That it does not
 * exceed the net salary minus the withholding tax stays a server-side rule (ACOMPTE_ELEVE).
 */
final readonly class Deposit
{
    private function __construct(private Money $amount) {}

    /**
     * @throws InvalidValue "deposit.not_positive"
     */
    public static function fromMoney(Money $amount): self
    {
        if ($amount->isZero()) {
            throw InvalidValue::notPositive('deposit');
        }

        return new self($amount);
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount->equals($other->amount);
    }
}
