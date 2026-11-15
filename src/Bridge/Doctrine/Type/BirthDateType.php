<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;

/**
 * Column type of the employer birth date ({@see CalendarDate}, ADR 0007: personal data): a DATE holding its "Y-m-d" string
 * value. A type of its own, so that an application encrypts the birth date alone (docs/doctrine.md) without touching the
 * employment and payment dates. A stored value the domain refuses is never quoted in the conversion error (lot 7 design §3.3).
 */
final class BirthDateType extends Type
{
    public const NAME = 'urssaf_cesu_birth_date';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabaseWithoutValue($value, CalendarDate::class, self::class, static fn(CalendarDate $value): string => $value->toString());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CalendarDate
    {
        return StringValueConversion::toPhpWithoutValue($value, self::class, CalendarDate::fromString(...));
    }
}
