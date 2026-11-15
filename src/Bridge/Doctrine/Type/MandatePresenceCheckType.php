<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Serializer\MandatePresenceCheckSerializer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandatePresenceCheck;

/**
 * JSON column type of an optional {@see MandatePresenceCheck}: a null check is stored as SQL NULL (a nullable group cannot be an embeddable).
 */
final class MandatePresenceCheckType extends Type
{
    public const NAME = 'urssaf_cesu_mandate_presence_check';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof MandatePresenceCheck) {
            throw InvalidType::new($value, self::class, [MandatePresenceCheck::class, 'null']);
        }

        return JsonConversion::encode(MandatePresenceCheckSerializer::toArray($value));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MandatePresenceCheck
    {
        return JsonConversion::decode(
            $value,
            self::class,
            MandatePresenceCheck::class,
            MandatePresenceCheckSerializer::fromArray(...),
        );
    }
}
