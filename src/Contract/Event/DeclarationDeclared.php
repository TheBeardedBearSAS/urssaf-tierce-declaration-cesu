<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event;

/**
 * URSSAF recorded the declaration (method 050 success, or manual resolution of an unknown outcome, ADR 0003).
 *
 * - aggregateId: the declaration record id;
 * - declarationId: the URSSAF identifier of the declaration (idDeclaration).
 */
final readonly class DeclarationDeclared extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.declaration.declared';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $declarationId,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
