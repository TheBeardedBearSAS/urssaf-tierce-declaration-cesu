<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Street number "numVoie" (api-reference §2.1): integer from 1 to 9999. Personal data (ADR 0007).
 */
final readonly class StreetNumber
{
    public const MIN = 1;
    public const MAX = 9999;

    private function __construct(private int $value) {}

    /**
     * @throws InvalidValue "street_number.out_of_range"
     */
    public static function fromInt(#[SensitiveParameter] int $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidValue::outOfRange('street_number', self::MIN, self::MAX);
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Personal data: masked in debug output (var_dump, print_r, dumpers). There is deliberately no __toString().
     *
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
