<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the 060 response `OutputActivationCesuPlus` (api-reference §2.8): the required `activationCesu` boolean.
 */
final readonly class CesuPlusActivationResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $payload): CesuPlusActivation
    {
        return CesuPlusActivation::of($payload->requireBool('activationCesu'));
    }
}
