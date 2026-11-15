<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * E-mail address validated with the contract pattern of "email" (api-reference §2.1, also "InfosSalarie.email", §2.7),
 * 256 characters at most. Kept verbatim (no case folding). Personal data (ADR 0007).
 */
final readonly class EmailAddress
{
    public const MAX_LENGTH = 256;
    private const SUBJECT = 'email_address';
    private const PATTERN = '/^[-A-Za-z0-9_]+(\.[-A-Za-z0-9_]+)*@[A-Za-z0-9]+((-|\.)[A-Za-z0-9]+)*\.[A-Za-z]+$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "email_address.too_long" or "email_address.invalid_format"
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'contract e-mail pattern');
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
