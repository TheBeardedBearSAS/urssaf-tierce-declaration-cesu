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
 * An employer account is tracked: registered through method 010 or imported (ADR 0012, api-reference §2.1).
 *
 * - aggregateId: the employer account id;
 * - cesuNumber: the Cesu number of the employer (not personal data, ADR 0007);
 * - origin: "registered" (010) or "imported".
 */
final readonly class EmployerRegistered extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.employer.registered';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $cesuNumber,
        public string $origin,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
