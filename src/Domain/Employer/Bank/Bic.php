<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * BIC "bic" of the bank details "Rib" (api-reference §2.1): structural pre-check only, spaces removed and upper-cased,
 * 1 to 11 alphanumeric characters. No checksum nor format rule: URSSAF validates them (§4.12). Personal data and never
 * persisted (ADR 0007).
 */
final readonly class Bic
{
    public const MAX_LENGTH = 11;
    private const PATTERN = '/^[A-Z0-9]+$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "bic.empty", "bic.too_long", "bic.invalid_characters"
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        $normalised = strtoupper(str_replace(' ', '', $value));
        if ($normalised === '') {
            throw InvalidValue::empty('bic');
        }
        if (\strlen($normalised) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong('bic', self::MAX_LENGTH);
        }
        if (preg_match(self::PATTERN, $normalised) !== 1) {
            throw InvalidValue::invalidCharacters('bic');
        }

        return new self($normalised);
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
