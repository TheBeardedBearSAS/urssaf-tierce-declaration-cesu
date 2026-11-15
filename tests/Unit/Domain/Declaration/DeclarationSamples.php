<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use DateInterval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ActivityNature;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalBasis;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\CesuPlusDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Coefficient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DepartmentalCouncil;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Deposit;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\EmploymentPeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerApproval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\HourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\PaidLeavePeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SalarySupplement;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementSubCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\BirthDepartment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Hand-written samples of the declaration scope (fictitious values and official documentation examples only).
 *
 * One static factory per sample, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class DeclarationSamples
{
    public const EMPLOYER_CESU_NUMBER = 'Z0003752390009';
    public const EMPLOYEE_CESU_NUMBER = '00000123456789';

    public static function now(): Instant
    {
        return Instant::fromIso8601('2026-11-15T10:00:00Z');
    }

    public static function later(int $seconds): Instant
    {
        $base = self::now()->toDateTimeImmutable();
        $interval = new DateInterval('PT' . abs($seconds) . 'S');

        return Instant::fromDateTime($seconds < 0 ? $base->sub($interval) : $base->add($interval));
    }

    public static function today(): CalendarDate
    {
        return CalendarDate::fromString('2026-11-15');
    }

    public static function date(string $date): CalendarDate
    {
        return CalendarDate::fromString($date);
    }

    public static function employerCesuNumber(): CesuNumber
    {
        return CesuNumber::fromString(self::EMPLOYER_CESU_NUMBER);
    }

    public static function employeeCesuNumber(): CesuNumber
    {
        return CesuNumber::fromString(self::EMPLOYEE_CESU_NUMBER);
    }

    public static function money(string $amount): Money
    {
        return Money::fromDecimalString($amount);
    }

    public static function days(string $days): HalfDays
    {
        return HalfDays::fromDecimalString($days);
    }

    public static function coefficient(string $value): Coefficient
    {
        return Coefficient::fromDecimalString($value);
    }

    public static function period(string $start = '2026-10-01', string $end = '2026-10-31'): EmploymentPeriod
    {
        return EmploymentPeriod::between(CalendarDate::fromString($start), CalendarDate::fromString($end));
    }

    public static function leave(string $start, string $end, string $days = '2'): PaidLeavePeriod
    {
        return PaidLeavePeriod::of(CalendarDate::fromString($start), CalendarDate::fromString($end), self::days($days));
    }

    public static function employee(EmploymentKind $kind, ?bool $paidLeaveIncluded): DeclaredEmployee
    {
        return new DeclaredEmployee(
            EmployeeByCesuNumber::of(self::employeeCesuNumber()),
            $kind,
            $paidLeaveIncluded,
            null,
            null,
            null,
            null,
        );
    }

    public static function supplement(): SalarySupplement
    {
        return SalarySupplement::of(
            SupplementCategory::SpecificHours,
            SupplementSubCategory::NightPresence,
            self::money('20.09'),
            self::money('12.5'),
            3,
        );
    }

    public static function hourlyIncome(): HourlyIncome
    {
        return HourlyIncome::of(self::money('12.5'), WorkedHours::of(100, 4, null), null, [], []);
    }

    public static function hourlyIncomeWithLeaveHours(int $paidLeaveHours): HourlyIncome
    {
        return HourlyIncome::of(self::money('12.5'), WorkedHours::of(100, 4, null), $paidLeaveHours, [], []);
    }

    public static function fosterIncome(): FosterCarerIncome
    {
        return new FosterCarerIncome(
            self::money('100'),
            self::days('10'),
            self::days('2.5'),
            self::days('3'),
            self::days('4'),
            self::coefficient('2.5'),
            self::coefficient('1.5'),
            self::coefficient('0.75'),
            self::coefficient('0.25'),
        );
    }

    public static function approval(): FosterCarerApproval
    {
        return FosterCarerApproval::of(
            ApprovalBasis::CertifiedApproval,
            DepartmentalCouncil::fromString('001'),
            null,
            self::date('2019-01-01'),
            self::date('2030-01-30'),
        );
    }

    public static function content(): DeclarationContent
    {
        return self::build(null, null, null, null, null);
    }

    /**
     * The default content with some parts replaced (a null argument keeps the default).
     */
    public static function build(
        ?HourlyIncome $hourlyIncome,
        ?DeclaredEmployee $employee,
        ?EmploymentPeriod $period,
        ?ActivityNature $nature,
        ?FosterCarerApproval $approval,
    ): DeclarationContent {
        return new DeclarationContent(
            self::employerCesuNumber(),
            $period ?? self::period(),
            self::date('2026-10-31'),
            $employee ?? self::employee(EmploymentKind::HomeEmployment, false),
            $hourlyIncome ?? self::hourlyIncome(),
            null,
            null,
            $approval,
            $nature,
        );
    }

    public static function contentWithoutIncome(): DeclarationContent
    {
        return new DeclarationContent(
            self::employerCesuNumber(),
            self::period(),
            self::date('2026-10-31'),
            self::employee(EmploymentKind::HomeEmployment, false),
            null,
            null,
            null,
            null,
            null,
        );
    }

    public static function contentWithFosterIncome(): DeclarationContent
    {
        return new DeclarationContent(
            self::employerCesuNumber(),
            self::period(),
            self::date('2026-10-31'),
            self::employee(EmploymentKind::FosterCarer, false),
            null,
            self::fosterIncome(),
            self::deposit(),
            self::approval(),
            null,
        );
    }

    public static function nirEmployee(): DeclaredEmployee
    {
        $identity = new EmployeeIdentity(
            PersonName::birthName('Martin'),
            PersonName::usageName('Durand'),
            PersonName::firstName('Camille'),
            CalendarDate::fromString('1980-02-03'),
            PersonName::birthCity('Lyon'),
            CountryCode::france(),
            BirthDepartment::fromString('069'),
        );

        return new DeclaredEmployee(
            EmployeeByNir::of(Nir::fromString('269054958815780'), $identity),
            EmploymentKind::FosterCarer,
            true,
            Civility::Female,
            new Address(
                StreetNumber::fromInt(12),
                null,
                StreetType::fromString('R'),
                AddressText::streetName('Exemple'),
                null,
                null,
                null,
                PostalCode::fromString('75001'),
            ),
            EmailAddress::fromString('camille.martin@example.org'),
            PhoneNumber::fromString('0102030405'),
        );
    }

    /**
     * Every optional part present, personal data included in the employee.
     */
    public static function fullContent(): DeclarationContent
    {
        $income = HourlyIncome::of(
            self::money('12.5'),
            WorkedHours::of(100, 4, 9),
            7,
            [
                self::supplement(),
                SalarySupplement::of(
                    SupplementCategory::Bonus,
                    SupplementSubCategory::Bonus,
                    self::money('50'),
                    null,
                    null,
                ),
            ],
            [self::leave('2026-10-05', '2026-10-06', '1.5'), self::leave('2026-10-20', '2026-10-21')],
        );

        return new DeclarationContent(
            self::employerCesuNumber(),
            self::period(),
            self::date('2026-10-31'),
            self::nirEmployee(),
            $income,
            self::fosterIncome(),
            self::deposit(),
            self::approval(),
            ActivityNature::Gardening,
        );
    }

    public static function standard(): StandardDeclaration
    {
        return StandardDeclaration::of(self::content());
    }

    public static function cesuPlus(): CesuPlusDeclaration
    {
        return CesuPlusDeclaration::of(self::build(null, null, null, ActivityNature::Gardening, null));
    }

    public static function snapshot(): DeclarationSnapshot
    {
        return self::snapshotOf(self::standard());
    }

    public static function snapshotOf(Declaration $declaration): DeclarationSnapshot
    {
        return DeclarationSnapshot::fromDeclaration($declaration);
    }

    public static function recordId(string $value = 'record-1'): DeclarationRecordId
    {
        return DeclarationRecordId::fromString($value);
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function externalReference(): ExternalReference
    {
        return ExternalReference::fromString('host-declaration-1');
    }

    public static function record(): DeclarationRecord
    {
        $record = DeclarationRecord::record(
            self::recordId(),
            self::tenantId(),
            self::externalReference(),
            self::snapshot(),
            self::now(),
        );
        $record->releaseEvents();

        return $record;
    }

    public static function result(): DeclarationResult
    {
        return new DeclarationResult(
            DeclarationId::fromString('209299542888'),
            Contributions::of(self::money('63.1'), self::money('20.09')),
            self::money('5'),
            self::money('1'),
            self::money('2'),
            self::money('3'),
            self::money('400'),
            self::money('300'),
            null,
            self::money('7'),
        );
    }

    public static function submissionError(string $code): SubmissionError
    {
        return SubmissionError::of($code, 'message', null);
    }

    /**
     * @param list<SubmissionError> $errors
     */
    public static function statusIn(
        DeclarationState $state,
        array $errors,
        ?DeclarationResult $result,
        ?OutcomeUnknownReason $reason,
    ): DeclarationStatus {
        return new DeclarationStatus(
            $state,
            2,
            DeclarationSamples::later(-60),
            $errors,
            $result?->declarationId() ?? ($state === DeclarationState::Declared ? DeclarationId::fromString('known') : null),
            $result,
            $reason ?? ($state === DeclarationState::OutcomeUnknown ? OutcomeUnknownReason::MayHaveReachedUrssaf : null),
            DeclarationSamples::later(-30),
        );
    }

    public static function deposit(): Deposit
    {
        return Deposit::fromMoney(self::money('10'));
    }
}
