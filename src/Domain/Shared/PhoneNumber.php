<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Phone number "telephone": exactly 10 digits (api-reference §2.1 max 10 chars and pattern "^$|.*[0-9]{10}.*", §2.7
 * exactly 10 chars and the same pattern). The empty alternative of the pattern means "absent" and is modelled by a
 * missing value, not by this object. Personal data (ADR 0007).
 */
final readonly class PhoneNumber
{
    private const SUBJECT = 'phone_number';
    private const PATTERN = '/^[0-9]{10}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "phone_number.invalid_format"
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '10 digits');
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
