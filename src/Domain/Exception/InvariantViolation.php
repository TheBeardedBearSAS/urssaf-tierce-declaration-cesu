<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception;

/**
 * Thrown when an operation would break a business invariant of an aggregate or a value object.
 */
final class InvariantViolation extends DomainException
{
    /**
     * @param array<string, scalar> $params
     */
    public static function because(string $violationCode, string $message, array $params = []): self
    {
        return new self($message, $violationCode, $params);
    }
}
