<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Settles a declaration whose outcome is unknown after the operator checked it on cesu.urssaf.fr (ADR 0003): either
 * URSSAF holds it ({@see self::asDeclared()}, with its declaration id) or it never received it
 * ({@see self::asNotReceived()}: the record is pending again, the host declares the salary again).
 */
final readonly class ResolveDeclarationOutcome
{
    private function __construct(
        public TenantId $tenantId,
        public DeclarationSelector $declaration,
        public ?DeclarationId $declarationId,
    ) {}

    public static function asDeclared(TenantId $tenantId, DeclarationSelector $declaration, DeclarationId $declarationId): self
    {
        return new self($tenantId, $declaration, $declarationId);
    }

    public static function asNotReceived(TenantId $tenantId, DeclarationSelector $declaration): self
    {
        return new self($tenantId, $declaration, null);
    }
}
