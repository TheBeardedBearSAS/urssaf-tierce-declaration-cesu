<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

/**
 * Outcome of a batch use case for one record: the record identifier (employer account id, declaration record id,
 * tenant id for a tenant-wide failure) and the related URSSAF codes.
 */
final readonly class BatchItem
{
    /**
     * @param list<string> $codes
     */
    public function __construct(
        public string $subject,
        public ItemOutcome $outcome,
        public array $codes = [],
    ) {}
}
