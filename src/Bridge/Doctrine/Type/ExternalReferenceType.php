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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

/**
 * Column type of {@see ExternalReference}: a VARCHAR holding its string value.
 */
final class ExternalReferenceType extends Type
{
    public const NAME = 'urssaf_cesu_external_reference';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, ExternalReference::class, self::class, static fn(ExternalReference $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ExternalReference
    {
        return StringValueConversion::toPhp($value, self::class, ExternalReference::fromString(...));
    }
}
