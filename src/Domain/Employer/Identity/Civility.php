<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Civility "codeCivilite" (api-reference §3.1): 1 = male, 2 = female.
 */
enum Civility: string
{
    case Male = '1';
    case Female = '2';

    /**
     * @throws InvalidValue "civility.unknown"
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::unknown('civility');
    }
}
