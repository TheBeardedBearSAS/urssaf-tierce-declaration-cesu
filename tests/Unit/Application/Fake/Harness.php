<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationContext;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
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
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * Wires the fakes together and builds aggregates in a given state. Time: NOW (2026-11-15T10:00:00Z, i.e. 11:00 in Paris).
 *
 * One builder per aggregate and one branch per state, hence the complexity and the method count.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class Harness
{
    public const TENANT = 'tenant-1';
    public const OTHER_TENANT = 'tenant-2';
    public const NOW = '2026-11-15T10:00:00Z';
    public const RECORDED_AT = '2026-11-15T09:00:00Z';

    public Journal $journal;
    public FixedClock $clock;
    public SequentialIdGenerator $ids;
    public RecordingEventPublisher $events;
    public RecordingTransactionManager $transactions;
    public UnitOfWork $unitOfWork;
    public JournalingEmployerAccounts $accounts;
    public JournalingMandates $mandates;
    public JournalingDeclarationRecords $declarations;
    public FakeCesuGateway $gateway;

    public function __construct(public WorkflowPolicy $policy = new WorkflowPolicy())
    {
        $this->journal = new Journal();
        $this->clock = new FixedClock(self::NOW);
        $this->ids = new SequentialIdGenerator();
        $this->events = new RecordingEventPublisher($this->journal);
        $this->transactions = new RecordingTransactionManager($this->journal);
        $this->unitOfWork = new UnitOfWork($this->transactions, $this->events);
        $this->accounts = new JournalingEmployerAccounts($this->journal);
        $this->mandates = new JournalingMandates($this->journal);
        $this->declarations = new JournalingDeclarationRecords($this->journal);
        $this->gateway = new FakeCesuGateway($this->journal);
    }

    public static function tenant(string $tenant = self::TENANT): TenantId
    {
        return TenantId::fromString($tenant);
    }

    public static function at(string $iso8601): Instant
    {
        return Instant::fromIso8601($iso8601);
    }

    /**
     * "Z0003752390009" for 1 (the employer of the valid declaration draft), "Y" and 13 digits otherwise.
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
     * Account "account-<number>" (external reference "employer-<number>", employer(number)) driven to the stage,
     * stored, events released.
     */
    public function account(int $number, EmployerStage $stage = EmployerStage::Registered, string $tenant = self::TENANT): EmployerAccount
    {
        $now = self::at(self::NOW);
        $account = EmployerAccount::register(
            EmployerAccountId::fromString('account-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('employer-' . $number),
            self::employer($number),
            self::at(self::RECORDED_AT),
        );
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
        $this->accounts->add($account);

        return $account;
    }

    /**
     * Mandate "mandate-<number>" of employer(number) (external reference "employer-<number>") driven to the state,
     * stored, events released.
     */
    public function mandate(int $number, MandateState $state = MandateState::Notified, string $tenant = self::TENANT): Mandate
    {
        $now = self::at(self::NOW);
        $mandate = Mandate::notify(
            MandateId::fromString('mandate-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('employer-' . $number),
            self::employer($number),
            self::at(self::RECORDED_AT),
        );
        if ($state === MandateState::Confirmed) {
            $mandate->applyStatus(true, $now);
        }
        if ($state === MandateState::Cancelled) {
            $mandate->cancel($now);
        }
        $mandate->releaseEvents();
        $this->mandates->add($mandate);

        return $mandate;
    }

    /**
     * The declaration of the valid draft, built with the harness policy (today = 2026-11-15).
     */
    public function declaration(): Declaration
    {
        return (new DeclarationFactory())->fromDraft(
            DraftFixtures::valid(),
            DeclarationContext::of($this->policy->today($this->clock->now()), $this->policy->allowFosterCarerDeclarations()),
        );
    }

    /**
     * Record "record-<number>" (external reference "declaration-<number>", snapshot of declaration()) driven to the
     * state with $attempts submission attempts (at least 1 for every state but pending), stored, events released.
     */
    public function record(int $number, DeclarationState $state, int $attempts = 1, string $tenant = self::TENANT): DeclarationRecord
    {
        $at = self::at(self::RECORDED_AT);
        $record = DeclarationRecord::record(
            DeclarationRecordId::fromString('record-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('declaration-' . $number),
            DeclarationSnapshot::fromDeclaration($this->declaration()),
            $at,
        );
        if ($state !== DeclarationState::Pending) {
            for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
                $record->startSubmission($at);
                if ($attempt < $attempts) {
                    $record->markFailed([], $at);
                    $record->retry($at, max(1, $attempts));
                }
            }
            self::finish($record, $state, $at, $number);
        }
        $record->releaseEvents();
        $this->declarations->add($record);

        return $record;
    }

    private static function finish(DeclarationRecord $record, DeclarationState $state, Instant $at, int $number): void
    {
        if ($state === DeclarationState::Declared) {
            $record->markDeclared(self::result($number), $at);
        }
        if ($state === DeclarationState::Rejected) {
            $record->markRejected([DeclarationSamples::submissionError('ERR_X')], $at);
        }
        if ($state === DeclarationState::AlreadyDeclared) {
            $record->markAlreadyDeclared([], $at);
        }
        if ($state === DeclarationState::OutcomeUnknown) {
            $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, [], $at);
        }
        if ($state === DeclarationState::Error) {
            $record->markFailed([], $at);
        }
        if ($state === DeclarationState::InvalidCredentials) {
            $record->markInvalidCredentials($at);
        }
    }

    /**
     * The amounts of DeclarationSamples::result() under a declaration id of its own ("2092995" and the number on 5
     * digits), so that several declared records of a tenant never share their URSSAF declaration id.
     */
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
}
