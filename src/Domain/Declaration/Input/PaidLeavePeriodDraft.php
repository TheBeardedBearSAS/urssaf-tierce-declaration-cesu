<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

/**
 * Raw paid leave period (`PeriodeCongePaye`); the days are a decimal string.
 */
final readonly class PaidLeavePeriodDraft
{
    public function __construct(
        public ?string $startDate,
        public ?string $endDate,
        public ?string $days,
    ) {}
}
