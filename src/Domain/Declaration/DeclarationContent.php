<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Content of a salary declaration (050, api-reference §2.7), common to both {@see Declaration} implementations.
 *
 * No invariant of its own: the cross-field rules are the specifications of {@see Specification\DeclarationRules}, so
 * that the factory can report all of them. The employer Cesu number is the path parameter of 050; the approval is
 * written inside `infosSalarie` by the wire mapper. The public constructor is the factory (more than 5 parts).
 */
final readonly class DeclarationContent
{
    public function __construct(
        private CesuNumber $employerCesuNumber,
        private EmploymentPeriod $employmentPeriod,
        private CalendarDate $paymentDate,
        private DeclaredEmployee $employee,
        private ?HourlyIncome $hourlyIncome,
        private ?FosterCarerIncome $fosterCarerIncome,
        private ?Deposit $deposit,
        private ?FosterCarerApproval $approval,
        private ?ActivityNature $activityNature,
    ) {}

    public function employerCesuNumber(): CesuNumber
    {
        return $this->employerCesuNumber;
    }

    public function employmentPeriod(): EmploymentPeriod
    {
        return $this->employmentPeriod;
    }

    /**
     * `dateVersement`.
     */
    public function paymentDate(): CalendarDate
    {
        return $this->paymentDate;
    }

    public function employee(): DeclaredEmployee
    {
        return $this->employee;
    }

    public function hourlyIncome(): ?HourlyIncome
    {
        return $this->hourlyIncome;
    }

    public function fosterCarerIncome(): ?FosterCarerIncome
    {
        return $this->fosterCarerIncome;
    }

    public function deposit(): ?Deposit
    {
        return $this->deposit;
    }

    public function approval(): ?FosterCarerApproval
    {
        return $this->approval;
    }

    public function activityNature(): ?ActivityNature
    {
        return $this->activityNature;
    }
}
