<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Serializer\EmployerStatusSerializer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;

/**
 * JSON column type of an optional {@see EmployerStatus}: the last method 020 snapshot, kept with its raw state codes. A null status is stored as SQL NULL, so the all-nullable group never needs an embeddable (which Doctrine cannot hydrate as null).
 */
final class EmployerStatusType extends Type
{
    public const NAME = 'urssaf_cesu_employer_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof EmployerStatus) {
            throw InvalidType::new($value, self::class, [EmployerStatus::class, 'null']);
        }

        return JsonConversion::encode(EmployerStatusSerializer::toArray($value));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EmployerStatus
    {
        return JsonConversion::decode(
            $value,
            self::class,
            EmployerStatus::class,
            EmployerStatusSerializer::fromArray(...),
        );
    }
}
