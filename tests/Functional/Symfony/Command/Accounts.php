<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;

/**
 * Tracked employers other than the one of the scenarios (fictitious Cesu numbers).
 */
final class Accounts
{
    private function __construct() {}

    public static function import(Harness $harness, string $tenant, string $reference, string $cesuNumber): EmployerAccountView
    {
        return $harness->get(ImportEmployerHandler::class)(new ImportEmployer(
            TenantId::fromString($tenant),
            ExternalReference::fromString($reference),
            Employer::of(CesuNumber::fromString($cesuNumber), CalendarDate::fromString('1980-06-15')),
        ));
    }

    /**
     * Configuration of a second tenant (the secret is an environment placeholder, set by the test kernel).
     *
     * @return array<string, mixed>
     */
    public static function withSecondTenant(): array
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        return ['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']]];
    }
}
