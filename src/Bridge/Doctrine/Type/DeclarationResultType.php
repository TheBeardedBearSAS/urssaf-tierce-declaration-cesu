<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Serializer\DeclarationResultSerializer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;

/**
 * JSON column type of an optional {@see DeclarationResult}: a null result is stored as SQL NULL. The amounts are personal data of the employee (ADR 0007, docs/doctrine.md), and a stored document the domain refuses is never quoted in the conversion error.
 */
final class DeclarationResultType extends Type
{
    public const NAME = 'urssaf_cesu_declaration_result';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof DeclarationResult) {
            throw InvalidType::new($value, self::class, [DeclarationResult::class, 'null']);
        }

        return JsonConversion::encode(DeclarationResultSerializer::toArray($value));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DeclarationResult
    {
        return JsonConversion::decode(
            $value,
            self::class,
            DeclarationResult::class,
            DeclarationResultSerializer::fromArray(...),
        );
    }
}
