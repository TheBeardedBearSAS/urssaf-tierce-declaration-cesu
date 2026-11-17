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

use Stringable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * URSSAF declaration identifier (`idDeclaration`, api-reference §2.7: 12 characters in the example, opaque).
 * Permissive: any non-blank value of at most 64 characters, kept verbatim.
 */
final readonly class DeclarationId implements Stringable
{
    public const MAX_LENGTH = 64;
    private const SUBJECT = 'declaration_id';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "declaration_id.empty" or "declaration_id.too_long"
     */
    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
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
