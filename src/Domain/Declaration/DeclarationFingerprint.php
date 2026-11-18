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

/**
 * Opaque fingerprint of the content of a declaration (SHA-256, 64 lowercase hexadecimal characters), used for
 * idempotency (ADR 0006 "same content"). Computed by {@see Declaration::fingerprint()}; it holds no personal data.
 */
final readonly class DeclarationFingerprint
{
    private const PATTERN = '/^[0-9a-f]{64}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "declaration_fingerprint.invalid_format"
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat('declaration_fingerprint', '64 lowercase hexadecimal characters');
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
}
