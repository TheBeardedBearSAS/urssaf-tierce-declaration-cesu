<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Free text of an address line (api-reference §2.1): street name "libelleVoie" (21 characters), complement
 * "complementLocalisation" (32), locality "lieuDit" (32). Kept verbatim. Personal data (ADR 0007).
 */
final readonly class AddressText
{
    public const STREET_NAME_MAX_LENGTH = 21;
    public const COMPLEMENT_MAX_LENGTH = 32;
    public const LOCALITY_MAX_LENGTH = 32;
    private const PATTERN = '/^\P{Cc}+$/Du';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "street_name.empty", "street_name.invalid_characters", "street_name.too_long"
     */
    public static function streetName(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'street_name', self::STREET_NAME_MAX_LENGTH);
    }

    /**
     * @throws InvalidValue "address_complement.empty", "address_complement.invalid_characters", "address_complement.too_long"
     */
    public static function complement(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'address_complement', self::COMPLEMENT_MAX_LENGTH);
    }

    /**
     * @throws InvalidValue "locality.empty", "locality.invalid_characters", "locality.too_long"
     */
    public static function locality(#[SensitiveParameter] string $value): self
    {
        return self::create($value, 'locality', self::LOCALITY_MAX_LENGTH);
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

    private static function create(string $value, string $subject, int $maxLength): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty($subject);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidCharacters($subject);
        }
        if (mb_strlen($value) > $maxLength) {
            throw InvalidValue::tooLong($subject, $maxLength);
        }

        return new self($value);
    }
}
