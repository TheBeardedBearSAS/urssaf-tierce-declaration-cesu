<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

/**
 * Meaning of an `ERR_METIER` error (api-reference §3.13, ADR 0005): one case per distinct text of the PDF v2.0.2.
 *
 * The enum holds no French text: the texts and the operations they belong to live in the infrastructure layer, which
 * classifies the descriptions. Use it (or {@see UrssafRequestRejected::is()}) rather than comparing error texts.
 *
 * One case per ERR_METIER text of the PDF, hence the case count.
 *
 * @mago-expect lint:too-many-enum-cases
 */
enum BusinessError: string
{
    /** The IBAN is invalid. */
    case IbanInvalid = 'iban_invalid';

    /** The IBAN prefix is not a SEPA one. */
    case IbanNotSepa = 'iban_not_sepa';

    /** The BIC is invalid. */
    case BicInvalid = 'bic_invalid';

    /** The bank details are blacklisted. */
    case BankDetailsBlacklisted = 'bank_details_blacklisted';

    /** The civility is missing. */
    case CivilityMissing = 'civility_missing';

    /** The last name is missing. */
    case LastNameMissing = 'last_name_missing';

    /** The last name is invalid. */
    case LastNameInvalid = 'last_name_invalid';

    /** The first name is missing. */
    case FirstNameMissing = 'first_name_missing';

    /** The first name is invalid. */
    case FirstNameInvalid = 'first_name_invalid';

    /** The legal form (company, association, parish...) cannot create a Cesu account. */
    case ForbiddenLegalForm = 'forbidden_legal_form';

    /** The birth date is missing. */
    case BirthDateMissing = 'birth_date_missing';

    /** The employer is under 16. */
    case EmployerUnderSixteen = 'employer_under_sixteen';

    /** The birth date is invalid (for example over 110 years ago). */
    case BirthDateInvalid = 'birth_date_invalid';

    /** The address is incomplete. */
    case AddressIncomplete = 'address_incomplete';

    /** The street number is missing. */
    case StreetNumberMissing = 'street_number_missing';

    /** The street name is missing. */
    case StreetNameMissing = 'street_name_missing';

    /** The postal code and municipality are invalid. */
    case MunicipalityInvalid = 'municipality_invalid';

    /** The phone number is invalid. */
    case PhoneInvalid = 'phone_invalid';

    /** The e-mail address is invalid. */
    case EmailInvalid = 'email_invalid';

    /** The e-mail address already belongs to a Cesu account. */
    case EmailAlreadyKnown = 'email_already_known';

    /** The birth date does not match the employer identified by the Cesu number. */
    case BirthDateMismatch = 'birth_date_mismatch';

    /** No link exists between the employer and the employee (no service was performed). */
    case NoEmployerEmployeeLink = 'no_employer_employee_link';

    /** The hourly wage is below the minimum. */
    case HourlyWageBelowMinimum = 'hourly_wage_below_minimum';

    /** Overtime needs at least 40 normal hours. */
    case OvertimeRequiresFortyNormalHours = 'overtime_requires_forty_normal_hours';

    /** The 50 % overtime needs at least 8 hours at 25 %. */
    case SecondTierOvertimeRequiresEightHours = 'second_tier_overtime_requires_eight_hours';

    /** The net hourly wage of a specific hour is below the minimum. */
    case SpecificHoursWageBelowMinimum = 'specific_hours_wage_below_minimum';

    /** An employee financial contribution is declared without any benefit in kind. */
    case ContributionWithoutBenefitsInKind = 'employee_contribution_without_benefits_in_kind';

    /** An employee financial contribution is declared without hours and net hourly wage. */
    case ContributionWithoutHoursAndWage = 'employee_contribution_without_hours_and_wage';

    /** The contributions on benefits in kind exceed the declared net salary. */
    case BenefitsInKindContributionsAboveNetSalary = 'benefits_in_kind_contributions_above_net_salary';

    /** No remuneration element is given. */
    case RemunerationMissing = 'remuneration_missing';

    /** The number of reception days exceeds 32. */
    case ReceptionDaysAboveLimit = 'reception_days_above_limit';

    /** The full-time daily wage is below the minimum. */
    case FullTimeDailyWageBelowMinimum = 'full_time_daily_wage_below_minimum';

    /** The part-time daily wage is below the minimum. */
    case PartTimeDailyWageBelowMinimum = 'part_time_daily_wage_below_minimum';

    /** The number of days of particular constraints exceeds 32. */
    case ParticularConstraintDaysAboveLimit = 'particular_constraint_days_above_limit';

    /** The net SMIC per day of particular constraints is out of range. */
    case ParticularConstraintSmicOutOfRange = 'particular_constraint_smic_out_of_range';

    /** The number of maintenance allowance days exceeds 32. */
    case MaintenanceAllowanceDaysAboveLimit = 'maintenance_allowance_days_above_limit';

    /** The guaranteed minimum per day of the maintenance allowance is out of range. */
    case GuaranteedMinimumOutOfRange = 'guaranteed_minimum_out_of_range';

    /** The contribution calculation failed (also: total net above the ceiling on 040). */
    case CalculationFailed = 'calculation_failed';

    /** Paid leave hours or periods are given while the 10 % rule includes them. */
    case PaidLeaveDeclaredWhileIncluded = 'paid_leave_declared_while_included';

    /** A declaration already exists for the employment period. */
    case DuplicateDeclaration = 'duplicate_declaration';

    /** The employment start date is after today. */
    case EmploymentStartsAfterToday = 'employment_starts_after_today';

    /** The employment start date is after the end date. */
    case EmploymentStartsAfterEnd = 'employment_starts_after_end';

    /** The employment period spans several months. */
    case EmploymentSpansSeveralMonths = 'employment_spans_several_months';

    /** The number of worked hours is missing. */
    case WorkedHoursMissing = 'worked_hours_missing';

    /** The declared net total is inconsistent. */
    case NetTotalInconsistent = 'net_total_inconsistent';

    /** The net hourly wage is inconsistent. */
    case NetHourlyWageInconsistent = 'net_hourly_wage_inconsistent';

    /** The net hourly wage is below the minimum. */
    case NetHourlyWageBelowMinimum = 'net_hourly_wage_below_minimum';

    /** The paid leave hours must be greater than zero. */
    case PaidLeaveHoursMissing = 'paid_leave_hours_missing';

    /** A paid leave period is incomplete. */
    case PaidLeavePeriodsIncomplete = 'paid_leave_periods_incomplete';

    /** A paid leave period ends before it starts. */
    case PaidLeavePeriodEndsBeforeStart = 'paid_leave_period_ends_before_start';

    /** Paid leave periods overlap. */
    case PaidLeavePeriodsOverlap = 'paid_leave_periods_overlap';

    /** Paid leave is outside the employment period. */
    case PaidLeaveOutsideEmployment = 'paid_leave_outside_employment';

    /** The total number of paid leave days must be greater than zero. */
    case PaidLeaveDaysMissing = 'paid_leave_days_missing';

    /** The employee contribution is not below the benefits in kind. */
    case ContributionAboveBenefitsInKind = 'employee_contribution_above_benefits_in_kind';

    /** The employee contribution is not below the remuneration excluding benefits in kind. */
    case ContributionAboveRemuneration = 'employee_contribution_above_remuneration';

    /** Hours are given without a net hourly wage. */
    case HoursWithoutHourlyWage = 'hours_without_hourly_wage';

    /** The nature of activity is missing. */
    case ActivityNatureMissing = 'activity_nature_missing';

    /** Declarations of family foster carers are not possible through the API. */
    case FosterCarerUnsupported = 'foster_carer_unsupported';

    /** The employer has no active bank details. */
    case EmployerBankDetailsInactive = 'employer_bank_details_inactive';

    /** The employer online account has an anomaly. */
    case EmployerOnlineAccountAnomaly = 'employer_online_account_anomaly';

    /** The employer account has an anomaly. */
    case EmployerAccountAnomaly = 'employer_account_anomaly';

    /** Cesu+ was deactivated at the employer request. */
    case CesuPlusDeactivatedByEmployer = 'cesu_plus_deactivated_by_employer';

    /** The employer account has an unpaid salary. */
    case UnpaidSalary = 'unpaid_salary';

    /** The employer account has unpaid contributions or withholding tax. */
    case UnpaidContributions = 'unpaid_contributions';

    /** The employee has no active bank details. */
    case EmployeeBankDetailsInactive = 'employee_bank_details_inactive';

    /** The employee online account has an anomaly. */
    case EmployeeOnlineAccountAnomaly = 'employee_online_account_anomaly';

    /** The employee account has an anomaly. */
    case EmployeeAccountAnomaly = 'employee_account_anomaly';

    /** The Cesu+ service is deactivated. */
    case CesuPlusDeactivated = 'cesu_plus_deactivated';

    /** Employer and employee have identical bank details. */
    case IdenticalBankDetails = 'identical_bank_details';

    /** Employer and employee have an identical civil status. */
    case IdenticalCivilStatus = 'identical_civil_status';

    /** The employer / employee pair has an anomaly. */
    case PairAnomaly = 'pair_anomaly';

    /** The Cesu+ declaration deadline (the 15th of the following month) is exceeded. */
    case CesuPlusDeadlineExceeded = 'cesu_plus_deadline_exceeded';

    /** The Cesu+ net salary ceiling for the period is exceeded. */
    case CesuPlusNetSalaryCeilingExceeded = 'cesu_plus_net_salary_ceiling_exceeded';

    /** The employer Cesu number is unknown. */
    case EmployerCesuNumberUnknown = 'employer_cesu_number_unknown';

    /** The employer / employee agreement is missing. */
    case PairAgreementMissing = 'pair_agreement_missing';

    /** The employee Cesu number is unknown. */
    case EmployeeCesuNumberUnknown = 'employee_cesu_number_unknown';

    /** No contract exists for the employer / employee pair. */
    case NoContract = 'no_contract';

    /** Cesu+ is already active for the pair. */
    case CesuPlusAlreadyActive = 'cesu_plus_already_active';

    /** Cesu+ is blocked for the employer. */
    case CesuPlusBlockedForEmployer = 'cesu_plus_blocked_for_employer';

    /** Cesu+ is blocked for the employee. */
    case CesuPlusBlockedForEmployee = 'cesu_plus_blocked_for_employee';

    /** Cesu+ is blocked for the pair. */
    case CesuPlusBlockedForPair = 'cesu_plus_blocked_for_pair';

    /** The employer has an APA / PCH exemption. */
    case ApaPchExemption = 'apa_pch_exemption';

    /** No employer matches the given information. */
    case EmployerNotFound = 'employer_not_found';

    /** The birth municipality code is missing. */
    case BirthMunicipalityCodeMissing = 'birth_municipality_code_missing';

    /** The individual is not recognised by the tax administration API (DGFIP R2P). */
    case NotRecognisedByTaxAdministration = 'not_recognised_by_tax_administration';

    /** The individual is not eligible for the AICI service. */
    case NotEligibleForAici = 'not_eligible_for_aici';

    /** The individual has no active Cesu+ service. */
    case CesuPlusNotActive = 'cesu_plus_not_active';
}
