<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Decimal;

/**
 * Non-money decimal of the foster carer income (`nbMinimumGaranti...`, `nbSmics...`, api-reference §2.7). The precision
 * is not published: at most 4 decimals are accepted (permissive choice), stored in ten-thousandths.
 */
final readonly class Coefficient
{
    private const SUBJECT = 'coefficient';
    private const DECIMALS = 4;
    private const MAX_INTEGER_DIGITS = 9;
    private const MIN_DECIMALS = 0;

    private function __construct(private int $scaled) {}

    /**
     * @throws InvalidValue "coefficient.invalid_format", "coefficient.negative", "coefficient.too_many_decimals" or
     *                      "coefficient.too_large"
     */
    public static function fromDecimalString(string $value): self
    {
        return new self(Decimal::parse($value, self::SUBJECT, self::DECIMALS, self::MAX_INTEGER_DIGITS));
    }

    /**
     * The value in ten-thousandths.
     */
    public function scaled(): int
    {
        return $this->scaled;
    }

    /**
     * Shortest form: "2", "2.5", "0.0001".
     */
    public function toDecimalString(): string
    {
        return Decimal::format($this->scaled, self::DECIMALS, self::MIN_DECIMALS);
    }

    /**
     * Checks a contract range (bounds included) and returns the same value.
     *
     * @throws InvalidValue "<subject>.out_of_range"
     */
    public function assertBetween(self $min, self $max, string $subject): self
    {
        if ($this->scaled < $min->scaled || $this->scaled > $max->scaled) {
            throw InvalidValue::outOfRange($subject, $min->toDecimalString(), $max->toDecimalString());
        }

        return $this;
    }

    public function equals(self $other): bool
    {
        return $this->scaled === $other->scaled;
    }
}
