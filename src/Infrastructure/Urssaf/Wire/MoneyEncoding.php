<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Amounts on the wire are JSON numbers in euros (api-reference §4.2).
 *
 * Encoding goes through decimal strings ({@see JsonDecimal}), so there is no float drift (63.10, 20.09). Decoding:
 * json_decode yields floats for decimals; they are rendered with two decimals (`round($v, 2)`: half away from zero
 * on the shortest decimal representation, then `%.2F`) before being parsed as decimal strings, hence 63.1 is read as
 * 6310 cents and never 6309.999...
 */
final class MoneyEncoding
{
    /**
     * Always two decimals: `10.00`, `63.10`, `0.00`.
     */
    public static function encode(Money $money): JsonDecimal
    {
        return JsonDecimal::fromString($money->toDecimalString());
    }

    /**
     * @throws UnexpectedUrssafResponse when the number is negative, too large or not finite
     */
    public static function decode(int|float $value): Money
    {
        if (\is_float($value) && !is_finite($value)) {
            throw UnexpectedUrssafResponse::invalidPayload('amount is not a finite number');
        }

        try {
            return Money::fromDecimalString(\is_float($value) ? \sprintf('%.2F', round($value, 2)) : (string) $value);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('amount is not a valid non-negative amount in euros', $exception);
        }
    }
}
