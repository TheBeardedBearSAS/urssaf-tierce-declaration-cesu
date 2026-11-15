<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * NIR of an employee "nir" (api-reference §2.3, §2.7): 15 characters, no pattern is published. Personal data, never
 * persisted (ADR 0007).
 */
final readonly class Nir
{
    private const PATTERN = '/^\S{15}$/Du';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "nir.invalid_format"
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat('nir', '15 characters');
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
