<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;

/**
 * Column type of {@see CalendarDate}: a DATE holding its "Y-m-d" string value.
 */
final class CalendarDateType extends Type
{
    public const NAME = 'urssaf_cesu_calendar_date';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, CalendarDate::class, self::class, static fn(CalendarDate $value): string => $value->toString());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CalendarDate
    {
        return StringValueConversion::toPhp($value, self::class, CalendarDate::fromString(...));
    }
}
