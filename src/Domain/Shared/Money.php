<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Non-negative amount in euro, stored as an integer number of cents (api-reference §4.2: URSSAF amounts have at most
 * 2 decimals and are exchanged as decimal strings on the wire, never as floats).
 */
final readonly class Money
{
    private const SUBJECT = 'money';
    private const DECIMALS = 2;
    private const MAX_INTEGER_DIGITS = 15;

    private function __construct(private int $cents) {}

    /**
     * @throws InvalidValue "money.negative"
     */
    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw InvalidValue::negative(self::SUBJECT);
        }

        return new self($cents);
    }

    /**
     * Strict parsing of "12", "12.5" or "12.50" (at most 2 decimals, "." separator, no sign).
     *
     * @throws InvalidValue "money.invalid_format", "money.negative", "money.too_many_decimals" or "money.too_large"
     */
    public static function fromDecimalString(string $amount): self
    {
        return new self(Decimal::parse($amount, self::SUBJECT, self::DECIMALS, self::MAX_INTEGER_DIGITS));
    }

    public function cents(): int
    {
        return $this->cents;
    }

    /**
     * Always 2 decimals: "12.50", "0.05".
     */
    public function toDecimalString(): string
    {
        return Decimal::format($this->cents, self::DECIMALS, self::DECIMALS);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * Checks a contract maximum (e.g. 999.99 or 99 999.99) and returns the same amount.
     *
     * @throws InvalidValue "<subject>.out_of_range" (from "0.00" to the maximum)
     */
    public function assertAtMost(self $max, string $subject): self
    {
        if ($this->isGreaterThan($max)) {
            throw InvalidValue::outOfRange($subject, '0.00', $max->toDecimalString());
        }

        return $this;
    }
}
