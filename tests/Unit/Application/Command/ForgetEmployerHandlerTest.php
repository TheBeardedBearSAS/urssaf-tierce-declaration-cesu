<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ForgetEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ForgetEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\EmployerErasureRefused;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerForgotten as DomainEmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\ErasurePolicies;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\JournalingOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\RacingDeclarationRecords;

/**
 * One method per scenario (ADR 0015, lot 10 design §3 and §7.3).
 *
 * @mago-expect lint:too-many-methods
 */
final class ForgetEmployerHandlerTest extends TestCase
{
    private const DEFAULT_STATES = 'pending,declared,rejected,already_declared,error,invalid_credentials';
    private const STATES_WITH_UNKNOWN = 'pending,declared,rejected,already_declared,outcome_unknown,error,invalid_credentials';

    public function testErasesTheRecordsTheMandatesAndTheAccountOfTheEmployerOnly(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateCancelled);
        $harness->mandate(1, MandateState::Cancelled);
        $harness->mandates->add(ContractSamples::mandateOfEmployer(1, 3, MandateState::Cancelled, Harness::TENANT, '2026-11-10T09:00:00Z'));
        $harness->mandates->add(ContractSamples::mandateOfEmployer(1, 7, MandateState::Notified, Harness::OTHER_TENANT));
        $harness->record(1, DeclarationState::Declared);
        $harness->record(2, DeclarationState::Pending);
        $harness->record(3, DeclarationState::Error);
        $harness->declarations->add(ContractSamples::recordOfEmployer(1, 4, DeclarationState::Rejected, Harness::TENANT, '2026-11-01T09:00:00Z'));
        $harness->record(8, DeclarationState::Declared, 1, Harness::OTHER_TENANT);
        $harness->account(2);
        $harness->mandate(2);
        $harness->declarations->add(ContractSamples::recordOfEmployer(2, 9, DeclarationState::Submitting));

        $erasure = self::handler($harness)(self::command(null, null));

        static::assertSame('account-1', $erasure->accountId);
        static::assertSame(['mandate-3', 'mandate-1'], $erasure->mandateIds);
        static::assertSame(['record-4', 'record-1', 'record-2', 'record-3'], $erasure->declarationRecordIds);
        static::assertSame(
            [
                'begin',
                'delete-by-employer:Z0003752390009:' . self::DEFAULT_STATES,
                'delete:mandate-3',
                'delete:mandate-1',
                'delete:account-1',
                'commit',
                'publish:EmployerForgotten',
            ],
            $harness->journal->entries(),
        );
        static::assertSame(['account-2'], array_map(static fn(EmployerAccount $account): string => $account->id()->value(), $harness->accounts->inner()->all()));
        static::assertSame(['mandate-2', 'mandate-7'], self::sorted(array_map(static fn(Mandate $mandate): string => $mandate->id()->value(), $harness->mandates->inner()->all())));
        static::assertSame(['record-8', 'record-9'], self::sorted(array_map(static fn(DeclarationRecord $record): string => $record->id()->value(), $harness->declarations->inner()->all())));
    }

    public function testRecordsTheForgottenEventWithTheErasedIdsAtTheClockInstant(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->mandate(1, MandateState::Cancelled);
        $harness->record(1, DeclarationState::Declared);

        self::handler($harness)(self::command(null, null));

        $events = $harness->events->events();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(DomainEmployerForgotten::class, $event);
        static::assertSame('account-1', $event->accountId()->value());
        static::assertSame(Harness::TENANT, $event->tenantId()->value());
        static::assertSame('employer-1', $event->externalReference()->value());
        static::assertSame(Harness::cesuNumber(1)->value(), $event->cesuNumber()->value());
        static::assertSame(['mandate-1'], array_map(static fn(MandateId $id): string => $id->value(), $event->mandateIds()));
        static::assertSame(['record-1'], array_map(static fn(DeclarationRecordId $id): string => $id->value(), $event->declarationRecordIds()));
        static::assertSame(Harness::NOW, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testAppendsOneIntegrationEventToTheOutboxInTheTransaction(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->record(1, DeclarationState::Declared);
        $unitOfWork = new UnitOfWork($harness->transactions, $harness->events, new IgnoredFailures(), new JournalingOutbox($harness->journal));

        self::build($harness, $harness->declarations, $harness->mandates, $unitOfWork)(self::command(null, null));

        static::assertSame(
            [
                'begin',
                'delete-by-employer:Z0003752390009:' . self::DEFAULT_STATES,
                'delete:account-1',
                'append:' . EmployerForgotten::TYPE,
                'commit',
                'publish:EmployerForgotten',
            ],
            $harness->journal->entries(),
        );
    }

    public function testAnEmployerWithoutMandateNorRecordIsErased(): void
    {
        $harness = new Harness();
        $harness->account(1);

        $erasure = self::handler($harness)(self::command(null, null));

        static::assertSame([], $erasure->mandateIds);
        static::assertSame([], $erasure->declarationRecordIds);
        static::assertSame([], $harness->accounts->inner()->all());
    }

    public function testTheEmployerCanBeSelectedByCesuNumberOrAccountId(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->account(2);

        $byCesu = self::handler($harness)(new ForgetEmployer(Harness::tenant(), EmployerSelector::byCesuNumber(Harness::cesuNumber(2)), null, null));
        $byId = self::handler($harness)(new ForgetEmployer(Harness::tenant(), EmployerSelector::byAccountId(EmployerAccountId::fromString('account-1')), null, null));

        static::assertSame('account-2', $byCesu->accountId);
        static::assertSame('account-1', $byId->accountId);
        static::assertSame([], $harness->accounts->inner()->all());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownEmployers(): iterable
    {
        yield 'no account' => [Harness::TENANT];
        yield 'account of another tenant' => [Harness::OTHER_TENANT];
    }

    #[DataProvider('provideUnknownEmployers')]
    public function testAnUnknownEmployerIsNotFound(string $accountTenant): void
    {
        $harness = new Harness();
        if ($accountTenant === Harness::OTHER_TENANT) {
            $harness->account(1, EmployerStage::Registered, $accountTenant);
        }

        try {
            self::handler($harness)(self::command(null, null));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('employer_account.not_found', $exception->violationCode());
        }

        static::assertSame(['begin', 'rollback'], $harness->journal->entries());
        static::assertCount($accountTenant === Harness::OTHER_TENANT ? 1 : 0, $harness->accounts->inner()->all());
    }

    /**
     * @return iterable<string, array{MandateState}>
     */
    public static function provideActiveMandates(): iterable
    {
        yield 'notified' => [MandateState::Notified];
        yield 'confirmed' => [MandateState::Confirmed];
    }

    #[DataProvider('provideActiveMandates')]
    public function testAnActiveMandateRefusesTheErasureByDefault(MandateState $state): void
    {
        $harness = self::harnessWithEverything($state);

        $refusal = self::refusal($harness, self::command(null, null));

        static::assertSame('employer_account.forget_active_mandate', $refusal->violationCode());
        static::assertSame(['employer_account_id' => 'account-1'], $refusal->params());
        self::assertNothingErased($harness);
    }

    public function testSubmissionsInFlightRefuseTheErasureWithTheirCount(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->record(1, DeclarationState::Submitting);
        $harness->record(2, DeclarationState::Submitting);
        $harness->record(3, DeclarationState::Declared);

        $refusal = self::refusal($harness, self::command(null, null));

        static::assertSame('employer_account.forget_submission_in_flight', $refusal->violationCode());
        static::assertSame(['employer_account_id' => 'account-1', 'count' => 2], $refusal->params());
        self::assertNothingErased($harness);
    }

    public function testUnknownOutcomesRefuseTheErasureWithTheirCountByDefault(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->record(1, DeclarationState::OutcomeUnknown);
        $harness->record(2, DeclarationState::OutcomeUnknown);
        $harness->record(3, DeclarationState::OutcomeUnknown);
        $harness->record(4, DeclarationState::Declared);

        $refusal = self::refusal($harness, self::command(null, null));

        static::assertSame('employer_account.forget_unknown_outcome', $refusal->violationCode());
        static::assertSame(['employer_account_id' => 'account-1', 'count' => 3], $refusal->params());
        self::assertNothingErased($harness);
    }

    public function testTheRefusalsAreCheckedInOrder(): void
    {
        $harness = self::harnessWithEverything(MandateState::Confirmed);
        $harness->record(5, DeclarationState::Submitting);
        $harness->record(6, DeclarationState::OutcomeUnknown);

        static::assertSame('employer_account.forget_active_mandate', self::refusal($harness, self::command(null, null))->violationCode());
        static::assertSame(
            'employer_account.forget_submission_in_flight',
            self::refusal($harness, self::command(true, null))->violationCode(),
        );
        static::assertSame(
            'employer_account.forget_unknown_outcome',
            self::refusal(self::withoutSubmission(), self::command(true, null))->violationCode(),
        );
    }

    public function testAnActiveMandateCanBeErasedWhenTheCommandAllowsIt(): void
    {
        $harness = self::harnessWithoutUnknown(MandateState::Confirmed);

        $erasure = self::handler($harness)(self::command(true, null));

        static::assertSame(['mandate-1'], $erasure->mandateIds);
        static::assertSame([], $harness->mandates->inner()->all());
        static::assertContains('delete-by-employer:Z0003752390009:' . self::DEFAULT_STATES, $harness->journal->entries());
    }

    public function testUnknownOutcomesAreErasedWhenTheCommandAllowsIt(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->record(1, DeclarationState::OutcomeUnknown);
        $harness->record(2, DeclarationState::Declared);

        $erasure = self::handler($harness)(self::command(null, true));

        static::assertSame(['record-1', 'record-2'], $erasure->declarationRecordIds);
        static::assertSame([], $harness->declarations->inner()->all());
        static::assertContains('delete-by-employer:Z0003752390009:' . self::STATES_WITH_UNKNOWN, $harness->journal->entries());
    }

    public function testASubmissionInFlightIsRefusedEvenWithBothOptIns(): void
    {
        $harness = new Harness(ErasurePolicies::of(true, true));
        $harness->account(1);
        $harness->mandate(1);
        $harness->record(1, DeclarationState::Submitting);

        $refusal = self::refusal($harness, self::command(true, true));

        static::assertSame('employer_account.forget_submission_in_flight', $refusal->violationCode());
        static::assertCount(1, $harness->declarations->inner()->all());
    }

    public function testThePolicyDecidesWhenTheCommandDoesNot(): void
    {
        $harness = self::harnessWithPolicy(MandateState::Notified, ErasurePolicies::of(true, true));

        $erasure = self::handler($harness)(self::command(null, null));

        static::assertSame(['mandate-1'], $erasure->mandateIds);
        static::assertContains('delete-by-employer:Z0003752390009:' . self::STATES_WITH_UNKNOWN, $harness->journal->entries());
    }

    public function testTheOptInsOfThePolicyAreIndependent(): void
    {
        $unknownOnly = self::harnessWithPolicy(MandateState::Notified, ErasurePolicies::of(true, false));
        $activeOnly = self::harnessWithPolicy(MandateState::Notified, ErasurePolicies::of(false, true));

        static::assertSame('employer_account.forget_active_mandate', self::refusal($unknownOnly, self::command(null, null))->violationCode());
        static::assertSame('employer_account.forget_unknown_outcome', self::refusal($activeOnly, self::command(null, null))->violationCode());
    }

    public function testTheCommandOverridesThePolicyBothWays(): void
    {
        $permissive = self::harnessWithPolicy(MandateState::Notified, ErasurePolicies::of(true, true));
        $strict = self::harnessWithEverything(MandateState::Notified);

        static::assertSame(
            'employer_account.forget_active_mandate',
            self::refusal($permissive, self::command(false, true))->violationCode(),
        );
        static::assertSame(
            'employer_account.forget_unknown_outcome',
            self::refusal($permissive, self::command(true, false))->violationCode(),
        );
        static::assertSame(['mandate-1'], self::handler($strict)(self::command(true, true))->mandateIds);
    }

    public function testARecordClaimedWhileTheEmployerIsErasedIsDetectedAndTheErasureFails(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $inner = new InMemoryDeclarationRecordRepository();
        $inner->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared));
        $records = new RacingDeclarationRecords($inner, ContractSamples::recordOfEmployer(1, 5, DeclarationState::Submitting));

        $failure = self::invariantViolation($harness, self::command(null, null), $records, $harness->mandates);

        static::assertSame('employer_account.concurrent_modification', $failure->violationCode());
        static::assertSame(['id' => 'account-1'], $failure->params());
        static::assertSame(['begin', 'delete:account-1', 'rollback'], $harness->journal->entries());
    }

    public function testAMandateThatSurvivesItsDeletionIsDetectedAndTheErasureFails(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->mandate(1, MandateState::Cancelled);
        $mandates = new class ($harness->mandates) implements MandateRepository {
            public function __construct(private readonly MandateRepository $inner) {}

            public function save(Mandate $mandate): void
            {
                $this->inner->save($mandate);
            }

            public function get(MandateId $id): Mandate
            {
                return $this->inner->get($id);
            }

            public function findActive(TenantId $tenantId, CesuNumber $cesuNumber): ?Mandate
            {
                return $this->inner->findActive($tenantId, $cesuNumber);
            }

            public function findByEmployer(TenantId $tenantId, CesuNumber $cesuNumber): array
            {
                return $this->inner->findByEmployer($tenantId, $cesuNumber);
            }

            public function delete(MandateId $id): void {}
        };

        $failure = self::invariantViolation($harness, self::command(null, null), $harness->declarations, $mandates);

        static::assertSame('employer_account.concurrent_modification', $failure->violationCode());
        static::assertSame(['id' => 'account-1'], $failure->params());
        static::assertSame(['begin', 'delete-by-employer:Z0003752390009:' . self::DEFAULT_STATES, 'delete:account-1', 'rollback'], $harness->journal->entries());
    }

    private static function handler(Harness $harness): ForgetEmployerHandler
    {
        return self::build($harness, $harness->declarations, $harness->mandates, $harness->unitOfWork);
    }

    private static function build(Harness $harness, DeclarationRecordRepository $records, MandateRepository $mandates, UnitOfWork $unitOfWork): ForgetEmployerHandler
    {
        return new ForgetEmployerHandler(
            new EmployerLocator($harness->accounts),
            $harness->accounts,
            $mandates,
            $records,
            $harness->clock,
            $unitOfWork,
            $harness->policy,
        );
    }

    private static function command(?bool $allowActiveMandate, ?bool $allowUnknownOutcomes): ForgetEmployer
    {
        return new ForgetEmployer(
            Harness::tenant(),
            EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1')),
            $allowActiveMandate,
            $allowUnknownOutcomes,
        );
    }

    /**
     * Account 1 with a mandate in the state, a declared record and a record of unknown outcome, default policy.
     */
    private static function harnessWithEverything(MandateState $mandate): Harness
    {
        return self::harnessWithPolicy($mandate, new WorkflowPolicy());
    }

    private static function harnessWithPolicy(MandateState $mandate, WorkflowPolicy $policy): Harness
    {
        $harness = self::harnessWithoutUnknown($mandate, $policy);
        $harness->record(2, DeclarationState::OutcomeUnknown);

        return $harness;
    }

    private static function harnessWithoutUnknown(MandateState $mandate, WorkflowPolicy $policy = new WorkflowPolicy()): Harness
    {
        $harness = new Harness($policy);
        $harness->account(1, EmployerStage::MandateConfirmed);
        $harness->mandate(1, $mandate);
        $harness->record(1, DeclarationState::Declared);

        return $harness;
    }

    private static function withoutSubmission(): Harness
    {
        return self::harnessWithEverything(MandateState::Confirmed);
    }

    private static function refusal(Harness $harness, ForgetEmployer $command): EmployerErasureRefused
    {
        try {
            self::handler($harness)($command);
        } catch (EmployerErasureRefused $exception) {
            return $exception;
        }

        static::fail('Expected EmployerErasureRefused.');
    }

    private static function invariantViolation(
        Harness $harness,
        ForgetEmployer $command,
        DeclarationRecordRepository $records,
        MandateRepository $mandates,
    ): InvariantViolation {
        try {
            self::build($harness, $records, $mandates, $harness->unitOfWork)($command);
        } catch (InvariantViolation $exception) {
            return $exception;
        }

        static::fail('Expected InvariantViolation.');
    }

    private static function assertNothingErased(Harness $harness): void
    {
        static::assertSame(['begin', 'rollback'], $harness->journal->entries());
        static::assertCount(1, $harness->accounts->inner()->all());
        static::assertGreaterThanOrEqual(1, \count($harness->declarations->inner()->all()));
        static::assertSame([], $harness->events->events());
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
