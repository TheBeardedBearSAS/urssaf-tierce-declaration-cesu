<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * postal code "codePostal" (api-reference §2.1): exactly 5 alphanumeric characters (only the length is published, §5.23).
 * Kept verbatim. Personal data (ADR 0007).
 */
final readonly class PostalCode
{
    private const PATTERN = '/^[A-Za-z0-9]{5}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "postal_code.invalid_format"
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat('postal_code', '5 alphanumeric characters');
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

    /**
     * Personal data: masked in debug output (var_dump, print_r, dumpers). There is deliberately no __toString().
     *
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
