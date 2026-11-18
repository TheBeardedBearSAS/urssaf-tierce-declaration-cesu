<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared;

use Stringable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * INSEE country code, 3 characters, France is "100" (api-reference §2.2, §2.7, §4.8). No pattern is published: only
 * what the contract refuses is refused (blank, whitespace, more than 3 characters). Kept verbatim.
 */
final readonly class CountryCode implements Stringable
{
    public const FRANCE = '100';
    public const MAX_LENGTH = 3;
    private const SUBJECT = 'country_code';
    private const PATTERN = '/^\S+$/Du';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "country_code.empty", "country_code.invalid_characters" or "country_code.too_long"
     */
    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidCharacters(self::SUBJECT);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
        }

        return new self($value);
    }

    public static function france(): self
    {
        return new self(self::FRANCE);
    }

    public function isFrance(): bool
    {
        return $this->value === self::FRANCE;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
