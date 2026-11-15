<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Name with the accented pattern published by the PDF (api-reference §2.1 "nom", also "prenom", "nomNaissance",
 * "nomUsage", "villeNaissance" in §2.2, §2.3, §2.7; §5.3), 30 characters at most. Kept verbatim: no transliteration and
 * no "starts / ends with a letter" rule (ADR 0000). Personal data (ADR 0007).
 */
final readonly class PersonName
{
    public const MAX_LENGTH = 30;
    private const PATTERN = "/^[a-zA-ZáÁàÀâÂäÄéÉèÈëËêÊìÌíÍïÏîÎñÑòÒóÓôÔöÖùÙúÚüÜûÛçÇýÝÿŸŒœÆæ'\\-\\s]+$/Du";

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "last_name.empty", "last_name.invalid_characters", "last_name.too_long"
     */
    public static function lastName(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'last_name');
    }

    /**
     * @throws InvalidValue "first_name.empty", "first_name.invalid_characters", "first_name.too_long"
     */
    public static function firstName(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'first_name');
    }

    /**
     * @throws InvalidValue "birth_name.empty", "birth_name.invalid_characters", "birth_name.too_long"
     */
    public static function birthName(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'birth_name');
    }

    /**
     * @throws InvalidValue "usage_name.empty", "usage_name.invalid_characters", "usage_name.too_long"
     */
    public static function usageName(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'usage_name');
    }

    /**
     * @throws InvalidValue "birth_city.empty", "birth_city.invalid_characters", "birth_city.too_long"
     */
    public static function birthCity(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'birth_city');
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

    private static function create(string $value, string $subject): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty($subject);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidCharacters($subject);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong($subject, self::MAX_LENGTH);
        }

        return new self($value);
    }
}
