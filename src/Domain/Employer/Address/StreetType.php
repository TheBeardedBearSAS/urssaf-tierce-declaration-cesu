<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address;

use Stringable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Street type "typeVoie" (api-reference §3.10): one of the 33 published codes. Not personal data (ADR 0007).
 */
final readonly class StreetType implements Stringable
{
    public const MAX_LENGTH = 3;
    private const CODES = [
        'ALL' => true,
        'AV' => true,
        'BAT' => true,
        'BD' => true,
        'CAN' => true,
        'CHS' => true,
        'CHE' => true,
        'CI' => true,
        'CL' => true,
        'C' => true,
        'COG' => true,
        'FG' => true,
        'HAM' => true,
        'IMB' => true,
        'IMP' => true,
        'LOT' => true,
        'MAI' => true,
        'MTE' => true,
        'PAS' => true,
        'PAR' => true,
        'PAV' => true,
        'PL' => true,
        'POR' => true,
        'Q' => true,
        'QU' => true,
        'RAM' => true,
        'RES' => true,
        'RTE' => true,
        'R' => true,
        'RUL' => true,
        'SEN' => true,
        'SQ' => true,
        'VO' => true,
    ];

    private function __construct(private string $value) {}

    /**
     * The code is upper-cased.
     *
     * @throws InvalidValue "street_type.unknown"
     */
    public static function fromString(string $value): self
    {
        $code = strtoupper($value);
        if (!\array_key_exists($code, self::CODES)) {
            throw InvalidValue::unknown('street_type');
        }

        return new self($code);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
