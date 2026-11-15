<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port;

use LogicException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;

final class CredentialsNotSerializable extends LogicException implements UrssafException
{
    public static function create(): self
    {
        return new self('API credentials must never be serialized.');
    }
}
