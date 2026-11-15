<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Thrown when an operation that needs a mandate (050, 060, 031) is refused locally because no active local mandate is
 * known for the employer (ADR 0009). Nothing was sent to URSSAF.
 *
 * Violation code: "mandate.required".
 */
final class MandateRequired extends DomainException
{
    public static function forEmployer(CesuNumber $cesuNumber, Operation $operation): self
    {
        return new self(
            \sprintf(
                'No active local mandate is known for the employer: method %s is refused locally (ADR 0009).',
                $operation->methodNumber(),
            ),
            'mandate.required',
            ['cesu_number' => $cesuNumber->value(), 'operation' => $operation->methodNumber()],
        );
    }
}
