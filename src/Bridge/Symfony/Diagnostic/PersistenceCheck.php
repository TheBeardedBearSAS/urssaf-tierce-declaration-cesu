<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic;

use Override;

/**
 * Tells which persistence the records live in: the "in_memory" driver loses them when the process ends.
 *
 * Source: lot 6 design §6.1 (new check), lot 7 design §8.3, ADR 0013. The tables of the "doctrine" driver are checked by
 * {@see DatabaseSchemaCheck}.
 */
final readonly class PersistenceCheck implements InstallationCheck
{
    private const NAME = 'persistence';

    /**
     * @param string $driver the "persistence.driver" option ("in_memory", "doctrine" or "custom")
     */
    public function __construct(private string $driver) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if ($this->driver === 'in_memory') {
            return [new CheckResult(self::NAME, '', CheckStatus::Warning, 'In-memory persistence: every record is lost when the process ends (tests and prototypes only).')];
        }

        if ($this->driver === 'doctrine') {
            return [new CheckResult(self::NAME, '', CheckStatus::Ok, 'Doctrine repositories (see the "database schema" check).')];
        }

        return [new CheckResult(self::NAME, '', CheckStatus::Ok, 'Repositories of the application ("custom" driver).')];
    }
}
