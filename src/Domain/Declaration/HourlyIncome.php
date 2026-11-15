<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Hourly income of a home employment (`RevenusEf`, api-reference §2.7): hourly wage (max 999.99), hours, paid leave
 * hours (0 to 200, null when not sent: this matters for the 10 % rule), supplements and paid leave periods.
 */
final readonly class HourlyIncome
{
    public const MAX_HOURLY_WAGE_CENTS = 99_999;
    public const MAX_PAID_LEAVE_HOURS = 200;

    /**
     * @param list<SalarySupplement> $supplements
     * @param list<PaidLeavePeriod>  $paidLeavePeriods
     */
    private function __construct(
        private Money $hourlyWage,
        private WorkedHours $hours,
        private ?int $paidLeaveHours,
        private array $supplements,
        private array $paidLeavePeriods,
    ) {}

    /**
     * @param list<SalarySupplement> $supplements
     * @param list<PaidLeavePeriod>  $paidLeavePeriods
     *
     * @throws InvalidValue "hourly_wage.out_of_range" or "paid_leave_hours.out_of_range"
     */
    public static function of(
        Money $hourlyWage,
        WorkedHours $hours,
        ?int $paidLeaveHours,
        array $supplements,
        array $paidLeavePeriods,
    ): self {
        $hourlyWage->assertAtMost(Money::fromCents(self::MAX_HOURLY_WAGE_CENTS), 'hourly_wage');
        if ($paidLeaveHours !== null && ($paidLeaveHours < 0 || $paidLeaveHours > self::MAX_PAID_LEAVE_HOURS)) {
            throw InvalidValue::outOfRange('paid_leave_hours', 0, self::MAX_PAID_LEAVE_HOURS);
        }

        return new self($hourlyWage, $hours, $paidLeaveHours, $supplements, $paidLeavePeriods);
    }

    public function hourlyWage(): Money
    {
        return $this->hourlyWage;
    }

    public function hours(): WorkedHours
    {
        return $this->hours;
    }

    public function paidLeaveHours(): ?int
    {
        return $this->paidLeaveHours;
    }

    /**
     * @return list<SalarySupplement>
     */
    public function supplements(): array
    {
        return $this->supplements;
    }

    /**
     * @return list<PaidLeavePeriod>
     */
    public function paidLeavePeriods(): array
    {
        return $this->paidLeavePeriods;
    }
}
