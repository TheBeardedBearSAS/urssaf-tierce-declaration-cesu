<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event;

/**
 * URSSAF answered that the declaration already exists (final, ADR 0006, ADR 0012): neither declared nor rejected by
 * this package, the host must act on it.
 *
 * - aggregateId: the declaration record id.
 */
final readonly class DeclarationAlreadyDeclared extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.declaration.already_declared';
}
