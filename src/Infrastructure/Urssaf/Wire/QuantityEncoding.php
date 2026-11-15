<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Coefficient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;

/**
 * Day counts and SMIC coefficients are JSON numbers that may carry a decimal part (api-reference §4.2): they are
 * emitted as exact literals, never through a float.
 */
final class QuantityEncoding
{
    /**
     * Shortest form: `0`, `2`, `2.5`.
     */
    public static function encodeHalfDays(HalfDays $days): JsonDecimal
    {
        return JsonDecimal::fromString($days->toDecimalString());
    }

    public static function encodeCoefficient(Coefficient $coefficient): JsonDecimal
    {
        return JsonDecimal::fromString($coefficient->toDecimalString());
    }
}
