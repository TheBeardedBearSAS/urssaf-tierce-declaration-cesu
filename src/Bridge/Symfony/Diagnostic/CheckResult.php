<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic;

/**
 * One line of the `urssaf:cesu:check` report.
 *
 * Source: reference Diagnostic\CheckResult, lot 6 design §6.1.
 */
final readonly class CheckResult
{
    /**
     * @param string $check   what is checked ("credentials", "token store"...)
     * @param string $subject the tenant or resource checked, empty when global
     * @param string $detail  what was found, and what to do when it is not ok
     */
    public function __construct(
        public string $check,
        public string $subject,
        public CheckStatus $status,
        public string $detail,
    ) {}
}
