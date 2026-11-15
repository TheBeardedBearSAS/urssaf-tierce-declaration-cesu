<?php

declare(strict_types=1);

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
