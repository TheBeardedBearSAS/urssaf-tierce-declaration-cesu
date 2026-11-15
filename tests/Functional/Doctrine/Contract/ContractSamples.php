<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * Builders of the aggregates used by the repository contracts: by number and state, fictitious values only. Like the
 * test harness of the unit tests (account-N / employer-N, mandate-N, record-N / declaration-N), but independent of it.
 *
 * Every builder returns the aggregate with its events released, except the "pending" ones. One builder per aggregate
 * and variant, one branch per state, hence the complexity and the method count.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class ContractSamples
{
    public const AT = '2026-11-10T09:00:00Z';
    public const TENANT = 'tenant-1';
    public const OTHER_TENANT = 'tenant-2';

    private function __construct() {}

    public static function tenant(string $tenant = self::TENANT): TenantId
    {
        return TenantId::fromString($tenant);
    }

    public static function at(string $iso8601 = self::AT): Instant
    {
        return Instant::fromIso8601($iso8601);
    }

    /**
     * "Z0003752390009" for 1, "Y" and 13 digits otherwise.
     */
    public static function cesuNumber(int $number): CesuNumber
    {
        return CesuNumber::fromString($number === 1 ? DeclarationSamples::EMPLOYER_CESU_NUMBER : \sprintf('Y%013d', $number));
    }

    public static function employer(int $number): Employer
    {
        return Employer::of(self::cesuNumber($number), CalendarDate::fromString('1975-01-30'));
    }

    /**
     * The account "account-N" (reference "employer-N") just registered at $at: its registration event is still pending.
     */
    public static function pendingAccount(int $number, string $tenant = self::TENANT, string $at = self::AT): EmployerAccount
    {
        return self::pendingAccountOfEmployer($number, $number, $tenant, $at);
    }

    /**
     * The account "account-N" of employer($employer) (reference "employer-<employer>") just registered at $at.
     */
    public static function pendingAccountOfEmployer(int $employer, int $number, string $tenant = self::TENANT, string $at = self::AT): EmployerAccount
    {
        return EmployerAccount::register(
            EmployerAccountId::fromString('account-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('employer-' . $employer),
            self::employer($employer),
            self::at($at),
        );
    }

    /**
     * The account driven to the stage at $at, events released.
     */
    public static function account(
        int $number,
        EmployerStage $stage = EmployerStage::Registered,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): EmployerAccount {
        return self::accountOfEmployer($number, $number, $stage, $tenant, $at);
    }

    /**
     * The account "account-N" of employer($employer) driven to the stage at $at, events released.
     */
    public static function accountOfEmployer(
        int $employer,
        int $number,
        EmployerStage $stage = EmployerStage::Registered,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): EmployerAccount {
        $account = self::pendingAccountOfEmployer($employer, $number, $tenant, $at);
        $now = self::at($at);
        if ($stage === EmployerStage::MandateNotified) {
            $account->markMandateNotified($now);
        }
        if ($stage === EmployerStage::MandateConfirmed) {
            $account->applyStatus(EmployerSamples::status(true, false), null, $now);
        }
        if ($stage === EmployerStage::AiciActive) {
            $account->applyStatus(EmployerSamples::status(true, true), null, $now);
        }
        if ($stage === EmployerStage::MandateCancelled) {
            $account->markMandateCancelled($now);
        }
        $account->releaseEvents();

        return $account;
    }

    /**
     * The mandate "mandate-N" of employer(N) (reference "employer-N") just notified at $at: its event is still pending.
     */
    public static function pendingMandate(int $number, string $tenant = self::TENANT, string $at = self::AT): Mandate
    {
        return self::pendingMandateOfEmployer($number, $number, $tenant, $at);
    }

    /**
     * The mandate "mandate-N" of employer($employer) (reference "employer-<employer>") just notified at $at.
     */
    public static function pendingMandateOfEmployer(int $employer, int $number, string $tenant = self::TENANT, string $at = self::AT): Mandate
    {
        return Mandate::notify(
            MandateId::fromString('mandate-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('employer-' . $employer),
            self::employer($employer),
            self::at($at),
        );
    }

    /**
     * A registered account (at $at) whose status was checked at $checkedAt (no mandate reported), events released.
     */
    public static function checkedAccount(int $number, string $checkedAt, string $tenant = self::TENANT, string $at = self::AT): EmployerAccount
    {
        $account = self::pendingAccount($number, $tenant, $at);
        $account->applyStatus(EmployerSamples::status(false, false), null, self::at($checkedAt));
        $account->releaseEvents();

        return $account;
    }

    /**
     * The mandate of employer($number) notified at $at and driven to the state one hour later, events released.
     */
    public static function mandate(
        int $number,
        MandateState $state = MandateState::Notified,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): Mandate {
        return self::mandateOfEmployer($number, $number, $state, $tenant, $at);
    }

    /**
     * The mandate "mandate-N" of employer($employer) notified at $at and driven to the state one hour later, events
     * released.
     */
    public static function mandateOfEmployer(
        int $employer,
        int $number,
        MandateState $state = MandateState::Notified,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): Mandate {
        $mandate = self::pendingMandateOfEmployer($employer, $number, $tenant, $at);
        $now = Instant::fromDateTime(self::at($at)->toDateTimeImmutable()->modify('+1 hour'));
        if ($state === MandateState::Confirmed) {
            $mandate->applyStatus(true, $now);
        }
        if ($state === MandateState::Cancelled) {
            $mandate->cancel($now);
        }
        $mandate->releaseEvents();

        return $mandate;
    }

    /**
     * The record "record-N" (reference "declaration-N") just recorded at $at: its event is still pending.
     */
    public static function pendingRecord(int $number, string $tenant = self::TENANT, string $at = self::AT): DeclarationRecord
    {
        return self::pendingRecordOfReference($number, $number, $tenant, $at);
    }

    /**
     * The record "record-N" of the external reference "declaration-<reference>" just recorded at $at.
     */
    public static function pendingRecordOfReference(int $reference, int $number, string $tenant = self::TENANT, string $at = self::AT): DeclarationRecord
    {
        return DeclarationRecord::record(
            DeclarationRecordId::fromString('record-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('declaration-' . $reference),
            DeclarationSamples::snapshot(),
            self::at($at),
        );
    }

    /**
     * The record driven to the state at $at with $attempts submission attempts (at least 1 for every state but pending),
     * events released. A declared record holds a result under the declaration id "2092995" and the number on 5 digits.
     */
    public static function record(
        int $number,
        DeclarationState $state,
        int $attempts = 1,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): DeclarationRecord {
        return self::recordOfReference($number, $number, $state, $attempts, $tenant, $at);
    }

    /**
     * The record "record-N" of the external reference "declaration-<reference>" (a declared one holds the result
     * numbered <reference>) driven to the state, like {@see self::record()}.
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function recordOfReference(
        int $reference,
        int $number,
        DeclarationState $state,
        int $attempts = 1,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): DeclarationRecord {
        $record = self::pendingRecordOfReference($reference, $number, $tenant, $at);
        $now = self::at($at);
        if ($state !== DeclarationState::Pending) {
            for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
                $record->startSubmission($now);
                if ($attempt < $attempts) {
                    $record->markFailed([], $now);
                    $record->retry($now, max(1, $attempts));
                }
            }
        }
        if ($state === DeclarationState::Declared) {
            $record->markDeclared(self::result($reference), $now);
        }
        if ($state === DeclarationState::Rejected) {
            $record->markRejected(self::errors(), $now);
        }
        if ($state === DeclarationState::AlreadyDeclared) {
            $record->markAlreadyDeclared(self::errors(), $now);
        }
        if ($state === DeclarationState::OutcomeUnknown) {
            $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, [], $now);
        }
        if ($state === DeclarationState::Error) {
            $record->markFailed([SubmissionError::of('urssaf.unavailable', 'The service is unavailable.', null)], $now);
        }
        if ($state === DeclarationState::InvalidCredentials) {
            $record->markInvalidCredentials($now);
        }
        $record->releaseEvents();

        return $record;
    }

    public static function result(int $number): DeclarationResult
    {
        $sample = DeclarationSamples::result();

        return new DeclarationResult(
            DeclarationId::fromString(\sprintf('2092995%05d', $number)),
            $sample->contributions(),
            $sample->withholdingTax(),
            $sample->employerContributionsDebit(),
            $sample->employerWithholdingTaxDebit(),
            $sample->employerSalaryDebit(),
            $sample->grossSalary(),
            $sample->netTaxableSalary(),
            $sample->salaryTransfer(),
            $sample->aiciAmount(),
        );
    }

    /**
     * @return list<SubmissionError>
     */
    public static function errors(): array
    {
        return [
            SubmissionError::of('ERR_METIER_1', 'First message.', 'First description.'),
            SubmissionError::of('ERR_METIER_2', 'Second message.', null),
        ];
    }
}
