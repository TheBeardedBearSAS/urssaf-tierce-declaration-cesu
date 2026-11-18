<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the 021 response `OutputVerificationSp` (api-reference §2.3). `inscrit` is required; `numeroCesu` is optional,
 * trimmed (the PDF example carries a leading space) and a blank value reads as absent.
 */
final readonly class EmployeeVerificationResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $payload): EmployeeVerificationResult
    {
        $registered = $payload->requireBool('inscrit');
        $cesuNumber = trim($payload->optionalString('numeroCesu') ?? '');
        if ($cesuNumber === '') {
            return EmployeeVerificationResult::of($registered, null);
        }

        try {
            return EmployeeVerificationResult::of($registered, CesuNumber::fromString($cesuNumber));
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('field "numeroCesu" is not valid', $exception);
        }
    }
}
