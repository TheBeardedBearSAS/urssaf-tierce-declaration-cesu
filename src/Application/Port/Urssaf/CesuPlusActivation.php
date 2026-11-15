<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

/**
 * Answer of method 060 (`OutputActivationCesuPlus`, api-reference §2.8): `activationCesu`.
 */
final readonly class CesuPlusActivation
{
    private function __construct(private bool $active) {}

    public static function of(bool $active): self
    {
        return new self($active);
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
