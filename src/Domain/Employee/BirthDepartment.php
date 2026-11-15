<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Birth department "departementNaissance" (api-reference §2.2, §2.3, §2.7, §4.8): 3 characters at most (e.g. 972, 07),
 * free text. Personal data (ADR 0007).
 */
final readonly class BirthDepartment
{
    public const MAX_LENGTH = 3;
    private const SUBJECT = 'birth_department';
    private const PATTERN = '/^\S+$/Du';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "birth_department.empty", "birth_department.invalid_characters", "birth_department.too_long"
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidCharacters(self::SUBJECT);
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
