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

/**
 * A check run by `urssaf:cesu:check`. Services tagged "urssaf_tierce_declaration_cesu.installation_check"
 * (autoconfigured) are run after the checks of the package, unless their tag has a positive priority.
 *
 * Source: reference Diagnostic\InstallationCheck, lot 6 design §6.1.
 */
interface InstallationCheck
{
    public const TAG = 'urssaf_tierce_declaration_cesu.installation_check';

    /**
     * @return list<CheckResult>
     */
    public function run(CheckContext $context): array;
}
