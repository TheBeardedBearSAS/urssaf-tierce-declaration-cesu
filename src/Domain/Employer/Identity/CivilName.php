<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Name of the civil status of method 070 ("nomNaissance", "nomUsage", "prenom", api-reference §2.9): 30 characters at
 * most and no published pattern, so no character set is enforced (only control characters are refused), unlike
 * {@see PersonName}. Kept verbatim. Personal data (ADR 0007).
 */
final readonly class CivilName
{
    public const MAX_LENGTH = 30;
    private const PATTERN = '/^\P{Cc}+$/Du';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "birth_name.empty", "birth_name.invalid_characters", "birth_name.too_long"
     */
    public static function birthName(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'birth_name');
    }

    /**
     * @throws InvalidValue "usage_name.empty", "usage_name.invalid_characters", "usage_name.too_long"
     */
    public static function usageName(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'usage_name');
    }

    /**
     * @throws InvalidValue "first_name.empty", "first_name.invalid_characters", "first_name.too_long"
     */
    public static function firstName(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'first_name');
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

    private static function create(string $value, string $subject): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty($subject);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidCharacters($subject);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong($subject, self::MAX_LENGTH);
        }

        return new self($value);
    }
}
