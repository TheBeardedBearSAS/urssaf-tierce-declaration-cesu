<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address;

use Stringable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Street letter "lettreVoie" (api-reference §3.9): a single letter from A to Z. Not personal data (ADR 0007).
 */
final readonly class StreetLetter implements Stringable
{
    private const PATTERN = '/^[A-Z]$/D';

    private function __construct(private string $value) {}

    /**
     * The letter is upper-cased.
     *
     * @throws InvalidValue "street_letter.invalid_format"
     */
    public static function fromString(string $value): self
    {
        $letter = strtoupper($value);
        if (preg_match(self::PATTERN, $letter) !== 1) {
            throw InvalidValue::invalidFormat('street_letter', 'a single letter from A to Z');
        }

        return new self($letter);
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
