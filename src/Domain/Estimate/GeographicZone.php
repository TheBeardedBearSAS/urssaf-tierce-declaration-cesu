<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Geographic zone of an estimate (`codeZoneGeographique`, api-reference §3.7 and §5.5): the ten values of the Swagger.
 * The labels of seven of them are not published, hence the neutral case names.
 */
enum GeographicZone: string
{
    case Metropolitan = 'NATIO';
    case Overseas = '97100';
    case AlsaceMoselle = 'ALSMO';
    case Dom = 'DOM';
    case Etran = 'ETRAN';
    case Zone97200 = '97200';
    case Zone97300 = '97300';
    case Zone97400 = '97400';
    case Zone97700 = '97700';
    case Zone97800 = '97800';

    /**
     * @throws InvalidValue "geographic_zone.unknown"
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::unknown('geographic_zone');
    }
}
