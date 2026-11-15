<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic;

use Override;

/**
 * Tells which persistence the records live in: the "in_memory" driver loses them when the process ends.
 *
 * Lot 7 (Doctrine bridge) adds the "doctrine" arm and the schema check.
 *
 * Source: lot 6 design §6.1 (new check), ADR 0013.
 */
final readonly class PersistenceCheck implements InstallationCheck
{
    private const NAME = 'persistence';

    /**
     * @param string $driver the "persistence.driver" option ("in_memory" or "custom": "doctrine" is refused at build time)
     */
    public function __construct(private string $driver) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if ($this->driver === 'in_memory') {
            return [new CheckResult(self::NAME, '', CheckStatus::Warning, 'In-memory persistence: every record is lost when the process ends (tests and prototypes only).')];
        }

        return [new CheckResult(self::NAME, '', CheckStatus::Ok, 'Repositories of the application ("custom" driver).')];
    }
}
