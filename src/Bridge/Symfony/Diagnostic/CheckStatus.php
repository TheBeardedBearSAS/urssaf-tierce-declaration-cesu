<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic;

/**
 * Outcome of one installation check; only a failure makes `urssaf:cesu:check` exit with 1.
 *
 * Source: reference Diagnostic\CheckStatus, lot 6 design §6.1.
 */
enum CheckStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Failure = 'failure';
    case Skipped = 'skipped';
}
