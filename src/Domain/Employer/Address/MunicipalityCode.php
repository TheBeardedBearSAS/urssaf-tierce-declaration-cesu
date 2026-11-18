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
 * INSEE municipality code "codeCommune" (api-reference §2.1): exactly 5 alphanumeric characters (only the length is published, §5.23).
 * Kept verbatim. Personal data (ADR 0007).
 */
final readonly class MunicipalityCode
{
    private const PATTERN = '/^[A-Za-z0-9]{5}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "municipality_code.invalid_format"
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat('municipality_code', '5 alphanumeric characters');
        }

        return new self($value);
    }

    public function value(): string
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
