<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port;

/**
 * The nine operations of the API Tierce Déclaration CESU. They all count towards one global hourly quota (ADR 0011):
 * there is no per-operation limit.
 */
enum Operation: string
{
    /** 010 POST /employeurs */
    case RegisterEmployer = 'register_employer';

    /** 020 GET /employeurs/{numeroCesu} */
    case VerifyEmployer = 'verify_employer';

    /** 021 GET /salaries */
    case VerifyEmployee = 'verify_employee';

    /** 030 POST /mandats */
    case RegisterMandate = 'register_mandate';

    /** 031 DELETE /mandats */
    case CancelMandate = 'cancel_mandate';

    /** 040 POST /estimer */
    case EstimateContributions = 'estimate_contributions';

    /** 050 POST /employeurs/{numeroCesu}/declarer */
    case DeclareSalary = 'declare_salary';

    /** 060 POST /employeurs/{numeroCesu}/cesuplus */
    case ActivateCesuPlus = 'activate_cesu_plus';

    /** 070 POST /employeurs/{numeroCesu}/aici */
    case ActivateAici = 'activate_aici';

    /**
     * Official number of the method in the URSSAF documentation ("010" to "070").
     */
    public function methodNumber(): string
    {
        return match ($this) {
            self::RegisterEmployer => '010',
            self::VerifyEmployer => '020',
            self::VerifyEmployee => '021',
            self::RegisterMandate => '030',
            self::CancelMandate => '031',
            self::EstimateContributions => '040',
            self::DeclareSalary => '050',
            self::ActivateCesuPlus => '060',
            self::ActivateAici => '070',
        };
    }

    /**
     * True for the operations that change nothing at URSSAF (the 040 simulation stores nothing).
     */
    public function isRead(): bool
    {
        return match ($this) {
            self::VerifyEmployer, self::VerifyEmployee, self::EstimateContributions => true,
            self::RegisterEmployer,
            self::RegisterMandate,
            self::CancelMandate,
            self::DeclareSalary,
            self::ActivateCesuPlus,
            self::ActivateAici => false,
        };
    }

    /**
     * False for 010, 050, 060 and 070: a transport error or a 5xx on them is an unknown outcome
     * ({@see Urssaf\OutcomeUnknown}); a 429 or a local rate-limit refusal is retryable for every operation because the
     * call provably did not reach URSSAF. The mandate operations (030, 031) are assumed idempotent (api-reference §5, point 17).
     */
    public function isSafeToRetry(): bool
    {
        return match ($this) {
            self::VerifyEmployer,
            self::VerifyEmployee,
            self::RegisterMandate,
            self::CancelMandate,
            self::EstimateContributions => true,
            self::RegisterEmployer, self::DeclareSalary, self::ActivateCesuPlus, self::ActivateAici => false,
        };
    }
}
