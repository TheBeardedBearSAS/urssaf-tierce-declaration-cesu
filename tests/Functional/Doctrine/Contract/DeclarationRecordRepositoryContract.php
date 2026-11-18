<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;

/**
 * Behaviour every {@see DeclarationRecordRepository} must have, independently of the storage. Identity-related behaviour
 * (copies versus managed instances) is deliberately not part of the contract. One method per scenario.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 * @mago-expect lint:too-many-methods
 */
trait DeclarationRecordRepositoryContract
{
    protected DeclarationRecordRepository $repository;

    abstract protected function createRepository(): DeclarationRecordRepository;

    /**
     * The transaction manager of the storage (the supersede scenario deletes and inserts in one transaction).
     */
    abstract protected function transactions(): TransactionManager;

    protected function setUp(): void
    {
        $this->repository = $this->createRepository();
    }

    /**
     * @param list<DeclarationRecord> $records
     *
     * @return list<string>
     */
    protected static function recordIds(array $records): array
    {
        return array_map(static fn(DeclarationRecord $record): string => $record->id()->value(), $records);
    }

    public function testAPendingRecordIsReadBack(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));

        $read = $this->repository->get(DeclarationRecordId::fromString('record-1'));

        static::assertSame('record-1', $read->id()->value());
        static::assertSame('tenant-1', $read->tenantId()->value());
        static::assertSame('declaration-1', $read->externalReference()->value());
        static::assertTrue($read->hasSameContentAs(DeclarationSamples::snapshot()));
        static::assertSame(DeclarationSamples::snapshot()->fingerprint()->value(), $read->snapshot()->fingerprint()->value());
        static::assertSame(DeclarationState::Pending, $read->state());
        static::assertSame(0, $read->attempts());
        static::assertNull($read->lastAttemptAt());
        static::assertSame([], $read->errors());
        static::assertNull($read->declarationId());
        static::assertNull($read->result());
        static::assertNull($read->outcomeUnknownReason());
        static::assertSame('2026-11-10T09:00:00Z', $read->createdAt()->toIso8601());
        static::assertSame('2026-11-10T09:00:00Z', $read->updatedAt()->toIso8601());
    }

    public function testADeclaredRecordIsReadBackWithItsResult(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Declared, 2, ContractSamples::TENANT, '2026-11-10T10:00:00Z'));

        $read = $this->repository->get(DeclarationRecordId::fromString('record-1'));

        static::assertSame(DeclarationState::Declared, $read->state());
        static::assertSame(2, $read->attempts());
        static::assertSame('2026-11-10T10:00:00Z', $read->lastAttemptAt()?->toIso8601());
        static::assertSame('209299500001', $read->declarationId()?->value());
        $result = $read->result();
        static::assertNotNull($result);
        $contributions = $result->contributions();
        static::assertNotNull($contributions);
        static::assertSame(6310, $contributions->employerContributions()->cents());
        static::assertSame(2009, $contributions->employeeContributions()->cents());
        static::assertSame(500, $result->withholdingTax()?->cents());
        static::assertSame(100, $result->employerContributionsDebit()?->cents());
        static::assertSame(200, $result->employerWithholdingTaxDebit()?->cents());
        static::assertSame(300, $result->employerSalaryDebit()?->cents());
        static::assertSame(40_000, $result->grossSalary()?->cents());
        static::assertSame(30_000, $result->netTaxableSalary()?->cents());
        static::assertNull($result->salaryTransfer());
        static::assertSame(700, $result->aiciAmount()?->cents());
        static::assertSame([], $read->errors());
        static::assertNull($read->outcomeUnknownReason());
    }

    public function testARejectedRecordIsReadBackWithItsErrors(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Rejected));

        $read = $this->repository->get(DeclarationRecordId::fromString('record-1'));

        static::assertSame(DeclarationState::Rejected, $read->state());
        static::assertSame(
            [['ERR_METIER_1', 'First message.', 'First description.'], ['ERR_METIER_2', 'Second message.', null]],
            array_map(
                static fn(SubmissionError $error): array => [$error->code(), $error->message(), $error->description()],
                $read->errors(),
            ),
        );
        static::assertNull($read->declarationId());
        static::assertNull($read->result());
    }

    public function testARecordInOutcomeUnknownIsReadBackWithItsReason(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::OutcomeUnknown));
        $stale = ContractSamples::record(2, DeclarationState::Submitting);
        $stale->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], ContractSamples::at('2026-11-10T10:00:00Z'));
        $this->repository->save($stale);

        static::assertSame(OutcomeUnknownReason::MayHaveReachedUrssaf, $this->repository->get(DeclarationRecordId::fromString('record-1'))->outcomeUnknownReason());
        static::assertSame(OutcomeUnknownReason::StaleSubmission, $this->repository->get(DeclarationRecordId::fromString('record-2'))->outcomeUnknownReason());
    }

    public function testTheEventsAreNeitherPersistedNorConsumedFromTheCaller(): void
    {
        $record = ContractSamples::pendingRecord(1);

        $this->repository->save($record);

        static::assertCount(1, $record->releaseEvents());
        static::assertSame([], $this->repository->get($record->id())->releaseEvents());
    }

    public function testASavedChangeIsReadBack(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $stored = $this->repository->get(DeclarationRecordId::fromString('record-1'));
        $stored->startSubmission(ContractSamples::at('2026-11-11T09:00:00Z'));
        $stored->markFailed([SubmissionError::of('urssaf.unavailable', 'Down.', null)], ContractSamples::at('2026-11-11T09:00:05Z'));

        $this->repository->save($stored);
        $read = $this->repository->get(DeclarationRecordId::fromString('record-1'));

        static::assertSame(DeclarationState::Error, $read->state());
        static::assertSame(1, $read->attempts());
        static::assertSame('2026-11-11T09:00:00Z', $read->lastAttemptAt()?->toIso8601());
        static::assertSame('2026-11-11T09:00:05Z', $read->updatedAt()->toIso8601());
        static::assertSame('2026-11-10T09:00:00Z', $read->createdAt()->toIso8601());
        static::assertSame(['urssaf.unavailable'], array_map(static fn(SubmissionError $error): string => $error->code(), $read->errors()));
    }

    public function testAnUnknownRecordIsNotFound(): void
    {
        try {
            $this->repository->get(DeclarationRecordId::fromString('missing'));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('declaration_record.not_found', $exception->violationCode());
            static::assertSame('missing', $exception->identifier());
        }
    }

    public function testTheSameExternalReferenceOfAnotherRecordOfTheTenantIsRefused(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $duplicate = DeclarationRecord::record(
            DeclarationRecordId::fromString('record-2'),
            ContractSamples::tenant(),
            ExternalReference::fromString('declaration-1'),
            DeclarationSamples::snapshot(),
            ContractSamples::at(),
        );

        try {
            $this->repository->save($duplicate);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.duplicate_external_reference', $exception->violationCode());
            static::assertSame(['external_reference' => 'declaration-1'], $exception->params());
        }
    }

    public function testTheSameDeclarationIdOfAnotherRecordOfTheTenantIsRefused(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Declared));
        $other = ContractSamples::record(2, DeclarationState::Submitting);
        $other->markDeclared(ContractSamples::result(1), ContractSamples::at('2026-11-10T10:00:00Z'));

        try {
            $this->repository->save($other);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('declaration_record.duplicate_declaration_id', $exception->violationCode());
            static::assertSame(['declaration_id' => '209299500001'], $exception->params());
        }
    }

    public function testTheSameExternalReferenceAndDeclarationIdInAnotherTenantAreAccepted(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Declared));
        $this->repository->save(ContractSamples::recordOfReference(1, 2, DeclarationState::Declared, 1, ContractSamples::OTHER_TENANT));

        static::assertSame('tenant-2', $this->repository->findByExternalReference(
            ContractSamples::tenant(ContractSamples::OTHER_TENANT),
            ExternalReference::fromString('declaration-1'),
        )?->tenantId()->value());
    }

    public function testSavingAnExistingRecordAgainIsAnUpdateNotADuplicate(): void
    {
        $record = ContractSamples::record(1, DeclarationState::Declared);
        $this->repository->save($record);
        $this->repository->save($record);

        static::assertSame(['record-1'], self::recordIds($this->repository->findByState(ContractSamples::tenant(), DeclarationState::Declared, 10)));
    }

    public function testTheFindersAreTenantScopedAndReturnNullWhenAbsent(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Declared));
        $tenant = ContractSamples::tenant();
        $other = ContractSamples::tenant(ContractSamples::OTHER_TENANT);
        $reference = ExternalReference::fromString('declaration-1');
        $declarationId = DeclarationId::fromString('209299500001');

        static::assertSame('record-1', $this->repository->findByExternalReference($tenant, $reference)?->id()->value());
        static::assertSame('record-1', $this->repository->findByDeclarationId($tenant, $declarationId)?->id()->value());
        static::assertNull($this->repository->findByExternalReference($other, $reference));
        static::assertNull($this->repository->findByDeclarationId($other, $declarationId));
        static::assertNull($this->repository->findByExternalReference($tenant, ExternalReference::fromString('declaration-2')));
        static::assertNull($this->repository->findByDeclarationId($tenant, DeclarationId::fromString('other')));
    }

    public function testTheRecordsOfAStateComeLeastRecentlyUpdatedFirstWithinTheLimitAndTheTenant(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Error, 1, ContractSamples::TENANT, '2026-11-12T09:00:00Z'));
        $this->repository->save(ContractSamples::record(2, DeclarationState::Error, 1, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));
        $this->repository->save(ContractSamples::record(3, DeclarationState::Error, 1, ContractSamples::TENANT, '2026-11-11T09:00:00Z'));
        $this->repository->save(ContractSamples::record(4, DeclarationState::Error, 1, ContractSamples::OTHER_TENANT, '2026-11-09T09:00:00Z'));
        $this->repository->save(ContractSamples::record(5, DeclarationState::Pending, 1, ContractSamples::TENANT, '2026-11-08T09:00:00Z'));
        $tenant = ContractSamples::tenant();

        static::assertSame(['record-2', 'record-3', 'record-1'], self::recordIds($this->repository->findByState($tenant, DeclarationState::Error, 10)));
        static::assertSame(['record-2', 'record-3'], self::recordIds($this->repository->findByState($tenant, DeclarationState::Error, 2)));
        static::assertSame(['record-5'], self::recordIds($this->repository->findByState($tenant, DeclarationState::Pending, 10)));
        static::assertSame([], $this->repository->findByState($tenant, DeclarationState::Declared, 10));
    }

    public function testTheStaleSubmissionsAreTheOnesAttemptedAtOrBeforeTheInstantOfEveryTenant(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Submitting, 1, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));
        $this->repository->save(ContractSamples::record(2, DeclarationState::Submitting, 1, ContractSamples::OTHER_TENANT, '2026-11-10T08:00:00Z'));
        $this->repository->save(ContractSamples::record(3, DeclarationState::Submitting, 1, ContractSamples::TENANT, '2026-11-10T09:00:01Z'));
        $this->repository->save(ContractSamples::record(4, DeclarationState::Pending, 1, ContractSamples::TENANT, '2026-11-10T07:00:00Z'));
        $this->repository->save(ContractSamples::record(5, DeclarationState::Error, 1, ContractSamples::TENANT, '2026-11-10T07:00:00Z'));
        $this->repository->save(ContractSamples::record(6, DeclarationState::Submitting, 1, ContractSamples::TENANT, '2026-11-10T07:30:00Z'));
        $olderThan = ContractSamples::at('2026-11-10T09:00:00Z');

        static::assertSame(['record-6', 'record-2', 'record-1'], self::recordIds($this->repository->findStaleSubmissions($olderThan, 10)));
        static::assertSame(['record-6', 'record-2'], self::recordIds($this->repository->findStaleSubmissions($olderThan, 2)));
        static::assertSame([], $this->repository->findStaleSubmissions(ContractSamples::at('2026-11-10T07:29:59Z'), 10));
        static::assertSame(['record-6'], self::recordIds($this->repository->findStaleSubmissions(ContractSamples::at('2026-11-10T07:59:59Z'), 10)));
        static::assertSame(['record-6', 'record-2'], self::recordIds($this->repository->findStaleSubmissions(ContractSamples::at('2026-11-10T08:00:00Z'), 10)));
    }

    public function testEveryStateIsCountedWithZeroForTheEmptyOnes(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Declared));
        $this->repository->save(ContractSamples::record(2, DeclarationState::Declared));
        $this->repository->save(ContractSamples::record(3, DeclarationState::Error));
        $this->repository->save(ContractSamples::record(4, DeclarationState::Declared, 1, ContractSamples::OTHER_TENANT));

        $counts = $this->repository->countByState(ContractSamples::tenant());

        static::assertEqualsCanonicalizing(
            array_map(static fn(DeclarationState $state): string => $state->value, DeclarationState::cases()),
            array_keys($counts),
        );
        static::assertSame(2, $counts['declared']);
        static::assertSame(1, $counts['error']);
        static::assertSame(0, $counts['pending']);
        static::assertSame(0, $counts['submitting']);
        static::assertSame(0, $counts['rejected']);
        static::assertSame(0, $counts['already_declared']);
        static::assertSame(0, $counts['outcome_unknown']);
        static::assertSame(0, $counts['invalid_credentials']);
        static::assertSame(0, array_sum($this->repository->countByState(ContractSamples::tenant('tenant-9'))));
        static::assertCount(\count(DeclarationState::cases()), $this->repository->countByState(ContractSamples::tenant('tenant-9')));
    }

    public function testTheTenantsWithAStateAreDistinctAndSorted(): void
    {
        static::assertSame([], $this->repository->tenantsWithState(DeclarationState::InvalidCredentials));

        $this->repository->save(ContractSamples::record(1, DeclarationState::InvalidCredentials, 1, 'tenant-b'));
        $this->repository->save(ContractSamples::record(2, DeclarationState::InvalidCredentials, 1, 'tenant-a'));
        $this->repository->save(ContractSamples::record(3, DeclarationState::InvalidCredentials, 1, 'tenant-b'));
        $this->repository->save(ContractSamples::record(4, DeclarationState::Declared, 1, 'tenant-c'));

        static::assertSame(
            ['tenant-a', 'tenant-b'],
            array_map(static fn(TenantId $tenant): string => $tenant->value(), $this->repository->tenantsWithState(DeclarationState::InvalidCredentials)),
        );
    }

    public function testAPendingRecordIsClaimedOnceWithOneMoreAttempt(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $now = ContractSamples::at('2026-11-12T10:00:00Z');
        $id = DeclarationRecordId::fromString('record-1');

        static::assertTrue($this->repository->claimForSubmission($id, $now));
        static::assertFalse($this->repository->claimForSubmission($id, $now));

        $read = $this->repository->get($id);
        static::assertSame(DeclarationState::Submitting, $read->state());
        static::assertSame(1, $read->attempts());
        static::assertSame('2026-11-12T10:00:00Z', $read->lastAttemptAt()?->toIso8601());
        static::assertSame('2026-11-12T10:00:00Z', $read->updatedAt()->toIso8601());
        static::assertSame([], $read->releaseEvents());
    }

    public function testTheClaimCountsTheAttemptsOfARequeuedRecord(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Error, 2));
        $stored = $this->repository->get(DeclarationRecordId::fromString('record-1'));
        $stored->retry(ContractSamples::at('2026-11-11T09:00:00Z'));
        $this->repository->save($stored);

        static::assertTrue($this->repository->claimForSubmission($stored->id(), ContractSamples::at('2026-11-12T10:00:00Z')));
        static::assertSame(3, $this->repository->get($stored->id())->attempts());
    }

    public function testAMissingOrNotPendingRecordIsNotClaimed(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Submitting));
        $this->repository->save(ContractSamples::record(2, DeclarationState::Declared));
        $now = ContractSamples::at('2026-11-12T10:00:00Z');

        static::assertFalse($this->repository->claimForSubmission(DeclarationRecordId::fromString('missing'), $now));
        static::assertFalse($this->repository->claimForSubmission(DeclarationRecordId::fromString('record-1'), $now));
        static::assertFalse($this->repository->claimForSubmission(DeclarationRecordId::fromString('record-2'), $now));
        static::assertSame(1, $this->repository->get(DeclarationRecordId::fromString('record-1'))->attempts());
        static::assertSame('2026-11-10T09:00:00Z', $this->repository->get(DeclarationRecordId::fromString('record-1'))->lastAttemptAt()?->toIso8601());
    }

    public function testTheClaimRecordsNoEventOnTheCallerInstance(): void
    {
        $record = ContractSamples::record(1, DeclarationState::Pending);
        $this->repository->save($record);

        $this->repository->claimForSubmission($record->id(), ContractSamples::at('2026-11-12T10:00:00Z'));

        static::assertSame([], $record->releaseEvents());
    }

    public function testADeletedRecordIsGoneAndDeletingAMissingOneIsANoOp(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Pending));
        $this->repository->save(ContractSamples::record(2, DeclarationState::Pending));

        $this->repository->delete(DeclarationRecordId::fromString('record-1'));
        $this->repository->delete(DeclarationRecordId::fromString('missing'));

        static::assertNull($this->repository->findByExternalReference(ContractSamples::tenant(), ExternalReference::fromString('declaration-1')));
        static::assertSame(['record-2'], self::recordIds($this->repository->findByState(ContractSamples::tenant(), DeclarationState::Pending, 10)));
        $this->expectException(RecordNotFound::class);
        $this->repository->get(DeclarationRecordId::fromString('record-1'));
    }

    public function testARejectedRecordIsSupersededByANewOneWithTheSameReferenceInOneTransaction(): void
    {
        $this->repository->save(ContractSamples::record(1, DeclarationState::Rejected));
        $replacement = DeclarationRecord::record(
            DeclarationRecordId::fromString('record-2'),
            ContractSamples::tenant(),
            ExternalReference::fromString('declaration-1'),
            DeclarationSamples::snapshot(),
            ContractSamples::at('2026-11-12T09:00:00Z'),
        );

        $rejectedId = DeclarationRecordId::fromString('record-1');

        $this->transactions()->transactional(function () use ($rejectedId, $replacement): void {
            $this->repository->delete($rejectedId);
            $this->repository->save($replacement);
        });

        static::assertSame(
            'record-2',
            $this->repository->findByExternalReference(ContractSamples::tenant(), ExternalReference::fromString('declaration-1'))?->id()->value(),
        );
        static::assertSame(['record-2'], self::recordIds($this->repository->findByState(ContractSamples::tenant(), DeclarationState::Pending, 10)));
        static::assertSame([], $this->repository->findByState(ContractSamples::tenant(), DeclarationState::Rejected, 10));
    }

    public function testCountByEmployerCountsEveryStateOfThatEmployerOnly(): void
    {
        $this->repository->save(ContractSamples::recordOfEmployer(2, 4, DeclarationState::Declared));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 5, DeclarationState::Declared, ContractSamples::OTHER_TENANT));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 2, DeclarationState::Declared));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 3, DeclarationState::Error));

        $counts = $this->repository->countByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1));

        static::assertSame(
            array_map(static fn(DeclarationState $state): string => $state->value, DeclarationState::cases()),
            array_keys($counts),
        );
        static::assertSame(
            ['pending' => 0, 'submitting' => 0, 'declared' => 2, 'rejected' => 0, 'already_declared' => 0, 'outcome_unknown' => 0, 'error' => 1, 'invalid_credentials' => 0],
            $counts,
        );
        static::assertSame(0, array_sum($this->repository->countByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(3))));
        static::assertCount(\count(DeclarationState::cases()), $this->repository->countByEmployer(ContractSamples::tenant('tenant-9'), ContractSamples::cesuNumber(1)));
    }

    public function testDeleteByEmployerDeletesTheRecordsInTheGivenStatesAndReturnsTheirIdsOldestFirst(): void
    {
        $this->repository->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared, ContractSamples::TENANT, '2026-11-12T09:00:00Z'));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 4, DeclarationState::Error, ContractSamples::TENANT, '2026-11-11T09:00:00Z'));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 3, DeclarationState::Pending, ContractSamples::TENANT, '2026-11-11T09:00:00Z'));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 2, DeclarationState::Rejected, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));

        $deleted = $this->repository->deleteByEmployer(
            ContractSamples::tenant(),
            ContractSamples::cesuNumber(1),
            [DeclarationState::Declared, DeclarationState::Error, DeclarationState::Pending, DeclarationState::Rejected],
        );

        static::assertSame(['record-2', 'record-3', 'record-4', 'record-1'], array_map(static fn(DeclarationRecordId $id): string => $id->value(), $deleted));
        static::assertSame(0, array_sum($this->repository->countByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1))));
    }

    public function testDeleteByEmployerKeepsTheRecordsInOtherStatesAndOfOtherEmployers(): void
    {
        $this->repository->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 2, DeclarationState::Submitting));
        $this->repository->save(ContractSamples::recordOfEmployer(2, 3, DeclarationState::Declared));
        $this->repository->save(ContractSamples::recordOfEmployer(1, 4, DeclarationState::Declared, ContractSamples::OTHER_TENANT));

        $deleted = $this->repository->deleteByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1), [DeclarationState::Declared]);

        static::assertSame(['record-1'], array_map(static fn(DeclarationRecordId $id): string => $id->value(), $deleted));
        static::assertSame(DeclarationState::Submitting, $this->repository->get(DeclarationRecordId::fromString('record-2'))->state());
        static::assertSame(DeclarationState::Declared, $this->repository->get(DeclarationRecordId::fromString('record-3'))->state());
        static::assertSame(DeclarationState::Declared, $this->repository->get(DeclarationRecordId::fromString('record-4'))->state());
        static::assertSame(1, $this->repository->countByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1))['submitting']);
        static::assertSame(0, $this->repository->countByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1))['declared']);
    }

    public function testDeleteByEmployerWithoutMatchReturnsNothing(): void
    {
        $this->repository->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared));

        static::assertSame([], $this->repository->deleteByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(2), [DeclarationState::Declared]));
        static::assertSame([], $this->repository->deleteByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1), [DeclarationState::Error]));
        static::assertSame('record-1', $this->repository->get(DeclarationRecordId::fromString('record-1'))->id()->value());
    }

    public function testADeletedByEmployerRecordIsNotFoundEvenIfItWasReadBefore(): void
    {
        $this->repository->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared));
        $id = DeclarationRecordId::fromString('record-1');
        $this->repository->get($id);

        $this->repository->deleteByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1), [DeclarationState::Declared]);

        $this->expectException(RecordNotFound::class);
        $this->repository->get($id);
    }

    public function testDeleteByEmployerFreesTheExternalReferenceAndTheDeclarationId(): void
    {
        $this->repository->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared));

        $this->repository->deleteByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1), [DeclarationState::Declared]);
        $this->repository->save(ContractSamples::recordOfReference(1, 2, DeclarationState::Declared));

        static::assertSame('record-2', $this->repository->findByExternalReference(ContractSamples::tenant(), ExternalReference::fromString('declaration-1'))?->id()->value());
        static::assertSame('record-2', $this->repository->findByDeclarationId(ContractSamples::tenant(), ContractSamples::result(1)->declarationId())?->id()->value());
    }
}
