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

use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * 030 and 031 answer an empty 200 (api-reference §2.4, §2.5; ADR 0002): an empty body, `{}` and `[]` (which are
 * indistinguishable once decoded) are accepted, anything else is not a documented answer.
 */
final readonly class MandateResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public function assertEmpty(Payload $payload): void
    {
        if (!$payload->isEmpty()) {
            throw UnexpectedUrssafResponse::invalidPayload('expected an empty body');
        }
    }
}
