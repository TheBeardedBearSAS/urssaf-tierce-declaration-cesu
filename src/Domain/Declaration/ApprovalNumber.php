<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Foster carer approval number (`numeroAgrement`, api-reference §2.7: max 20 characters, no pattern published).
 * Personal data (ADR 0007): masked in debug output, no `__toString()`.
 */
final readonly class ApprovalNumber
{
    public const MAX_LENGTH = 20;
    private const SUBJECT = 'approval_number';

    private function __construct(private string $value) {}

    /**
     * Kept verbatim.
     *
     * @throws InvalidValue "approval_number.empty" or "approval_number.too_long"
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
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
