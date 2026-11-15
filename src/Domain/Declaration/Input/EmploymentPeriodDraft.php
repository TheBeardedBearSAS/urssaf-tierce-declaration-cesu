<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

/**
 * Raw employment period (`dateDebut`, `dateFin`). An empty string counts as missing.
 */
final readonly class EmploymentPeriodDraft
{
    public function __construct(
        public ?string $startDate,
        public ?string $endDate,
    ) {}
}
