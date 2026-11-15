<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Salary supplement (`ComplementSalaire`, api-reference §2.7 and §3.8): category and sub-category (which must belong
 * together), amount (max 99 999.99), optional hourly wage (max 999.99) and hours (0 to 999).
 */
final readonly class SalarySupplement
{
    public const MAX_AMOUNT_CENTS = 9_999_999;
    public const MAX_HOURLY_WAGE_CENTS = 99_999;
    public const MAX_HOURS = 999;

    private function __construct(
        private SupplementCategory $category,
        private SupplementSubCategory $subCategory,
        private Money $amount,
        private ?Money $hourlyWage,
        private ?int $hours,
    ) {}

    /**
     * @throws InvalidValue "salary_supplement.unknown_pair", "supplement_amount.out_of_range",
     *                      "hourly_wage.out_of_range" or "supplement_hours.out_of_range"
     */
    public static function of(
        SupplementCategory $category,
        SupplementSubCategory $subCategory,
        Money $amount,
        ?Money $hourlyWage,
        ?int $hours,
    ): self {
        if ($subCategory->category() !== $category) {
            throw InvalidValue::because(
                'salary_supplement.unknown_pair',
                'The salary supplement sub-category does not belong to the category.',
            );
        }
        $amount->assertAtMost(Money::fromCents(self::MAX_AMOUNT_CENTS), 'supplement_amount');
        $hourlyWage?->assertAtMost(Money::fromCents(self::MAX_HOURLY_WAGE_CENTS), 'hourly_wage');
        if ($hours !== null && ($hours < 0 || $hours > self::MAX_HOURS)) {
            throw InvalidValue::outOfRange('supplement_hours', 0, self::MAX_HOURS);
        }

        return new self($category, $subCategory, $amount, $hourlyWage, $hours);
    }

    public function category(): SupplementCategory
    {
        return $this->category;
    }

    public function subCategory(): SupplementSubCategory
    {
        return $this->subCategory;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function hourlyWage(): ?Money
    {
        return $this->hourlyWage;
    }

    public function hours(): ?int
    {
        return $this->hours;
    }
}
