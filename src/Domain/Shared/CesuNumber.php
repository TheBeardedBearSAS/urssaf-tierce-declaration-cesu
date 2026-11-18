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
 * Cesu number of an employer or of an employee ("numeroCesu"): alphanumeric, exactly 14 characters (api-reference §2.1
 * response, §2.2, §2.4, §2.7, §2.8). Kept verbatim (no case folding). An identifier, not personal data: it is carried
 * by integration events (ADR 0012).
 */
final readonly class CesuNumber implements Stringable
{
    public const LENGTH = 14;
    private const SUBJECT = 'cesu_number';
    private const PATTERN = '/^[A-Za-z0-9]{' . self::LENGTH . '}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "cesu_number.invalid_format"
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '14 alphanumeric characters');
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

    public function __toString(): string
    {
        return $this->value;
    }
}
