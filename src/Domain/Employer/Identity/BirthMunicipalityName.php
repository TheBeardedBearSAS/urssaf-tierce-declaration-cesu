<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Birth municipality name of method 070 ("libelleCommuneNaissance", api-reference §2.9): 60 characters at most and no
 * published pattern, so only control characters are refused. Kept verbatim. Personal data (ADR 0007).
 */
final readonly class BirthMunicipalityName
{
    public const MAX_LENGTH = 60;
    private const SUBJECT = 'birth_municipality_name';
    private const PATTERN = '/^\P{Cc}+$/Du';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "birth_municipality_name.empty", "birth_municipality_name.invalid_characters",
     *                      "birth_municipality_name.too_long"
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
