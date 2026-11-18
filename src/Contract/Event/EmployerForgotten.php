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
 * The employer account, its mandates and its declaration records were erased from the package store (GDPR,
 * ForgetEmployer, ADR 0015): erase your copies. Nothing was erased at URSSAF.
 *
 * - aggregateId: the (erased) employer account id;
 * - cesuNumber: the Cesu number of the employer, as in every employer and mandate event;
 * - mandateIds: the erased mandate ids (aggregateId of the mandate events), oldest notification first;
 * - declarationRecordIds: the erased declaration record ids (aggregateId of the declaration events), oldest first.
 */
final readonly class EmployerForgotten extends IntegrationEvent
{
    public const TYPE = 'urssaf.cesu.employer.forgotten';

    /**
     * @param list<string> $mandateIds
     * @param list<string> $declarationRecordIds
     */
    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $cesuNumber,
        public array $mandateIds,
        public array $declarationRecordIds,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
