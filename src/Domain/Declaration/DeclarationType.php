<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Declaration type (`typeDeclaration`, api-reference §3.3): standard (0, the wire default) or Cesu+ (1).
 */
enum DeclarationType: int
{
    case Standard = 0;
    case CesuPlus = 1;

    /**
     * @throws InvalidValue "declaration_type.unknown"
     */
    public static function fromCode(int $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::unknown('declaration_type');
    }
}
