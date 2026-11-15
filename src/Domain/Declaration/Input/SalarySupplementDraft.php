<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

/**
 * Raw salary supplement (`ComplementSalaire`).
 */
final readonly class SalarySupplementDraft
{
    public function __construct(
        public ?string $category,
        public ?string $subCategory,
        public ?string $amount,
        public ?string $hourlyWage,
        public ?int $hours,
    ) {}
}
