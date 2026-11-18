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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\DeclarationAlreadyRecorded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\EmploymentPeriodDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\HourlyIncomeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service\DeclarationPolicies;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;

/**
 * One test per cell of the re-entry table and per local refusal, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class DeclareSalaryHandlerTest extends TestCase
{
    private const NEW_JOURNAL = [
        'begin',
        'save:id-1',
        'commit',
        'publish:DeclarationRecorded',
        'claim:id-1',
        'call:050',
        'begin',
        'save:id-1',
        'commit',
        'publish:DeclarationDeclared',
    ];

    public function testANewDeclarationIsRecordedThenSent(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);
        $harness->gateway->willDeclare(Harness::result(1));

        $submission = $this->declare($harness);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('id-1', $submission->declaration->recordId);
        static::assertSame('tenant-1', $submission->declaration->tenantId);
        static::assertSame('declaration-1', $submission->declaration->externalReference);
        static::assertSame('declared', $submission->declaration->status->state);
        static::assertSame(self::NEW_JOURNAL, $harness->journal->entries());
        static::assertSame('2026-11-15T10:00:00Z', $submission->declaration->createdAt);
        static::assertEquals([$harness->declaration()], $harness->gateway->declarations());
    }

    public function testTheRecordKeepsTheSnapshotOfTheDeclaration(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);
        $harness->gateway->willDeclare(Harness::result(1));

        $this->declare($harness);

        $stored = $harness->declarations->inner()->get(DeclarationRecordId::fromString('id-1'));
        static::assertSame(Harness::cesuNumber(1)->value(), $stored->snapshot()->employerCesuNumber()->value());
        static::assertTrue($stored->hasSameContentAs($harness->record(9, DeclarationState::Pending)->snapshot()));
    }

    public function testAnInvalidDraftIsRefusedBeforeAnythingIsRecorded(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);

        try {
            $this->declareDraft($harness, DraftFixtures::empty());
            static::fail('Expected ValidationFailed.');
        } catch (ValidationFailed $refusal) {
            static::assertFalse($refusal->result()->isValid());
        }

        $this->assertNothingHappened($harness);
    }

    public function testAnEmploymentStartingTodayInTheBusinessTimeZoneIsAccepted(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);
        $harness->clock->moveTo('2026-11-14T23:30:00Z');
        $draft = self::draftStartingToday();
        $harness->gateway->willDeclare(Harness::result(1));

        $submission = $this->declareDraft($harness, $draft);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
    }

    public function testTheBusinessTimeZoneOfThePolicyDecidesWhatTodayIs(): void
    {
        $harness = new Harness(DeclarationPolicies::inTimeZone('UTC'));
        $harness->mandate(1, MandateState::Confirmed);
        $harness->clock->moveTo('2026-11-14T23:30:00Z');
        $draft = self::draftStartingToday();

        try {
            $this->declareDraft($harness, $draft);
            static::fail('Expected ValidationFailed.');
        } catch (ValidationFailed $refusal) {
            static::assertSame('employment_period.starts_after_today', $refusal->result()->errors()[0]->code());
        }

        $this->assertNothingHappened($harness);
    }

    public function testAFosterCarerDeclarationIsRefusedUnlessThePolicyAllowsIt(): void
    {
        $draft = DraftFixtures::withParts(null, null, DraftFixtures::employee('AF'), DraftFixtures::fosterIncome(), DraftFixtures::approval());
        $refused = new Harness();
        $refused->mandate(1, MandateState::Confirmed);

        try {
            $this->declareDraft($refused, $draft);
            static::fail('Expected ValidationFailed.');
        } catch (ValidationFailed $refusal) {
            static::assertSame('declaration.foster_carer_unsupported', $refusal->result()->errors()[0]->code());
        }
        $this->assertNothingHappened($refused);

        $allowed = new Harness(DeclarationPolicies::allowingFosterCarers());
        $allowed->mandate(1, MandateState::Confirmed);
        $allowed->gateway->willDeclare(Harness::result(1));

        static::assertSame(ItemOutcome::Declared, $this->declareDraft($allowed, $draft)->outcome);
    }

    public function testWithoutMandateNothingIsRecordedNorSent(): void
    {
        $harness = new Harness();

        try {
            $this->declare($harness);
            static::fail('Expected MandateRequired.');
        } catch (MandateRequired $refusal) {
            static::assertSame(
                ['cesu_number' => Harness::cesuNumber(1)->value(), 'operation' => '050'],
                $refusal->params(),
            );
        }

        $this->assertNothingHappened($harness);
    }

    public function testTheMandateOfAnotherEmployerDoesNotCount(): void
    {
        $harness = new Harness();
        $harness->mandate(2, MandateState::Confirmed);

        $this->expectException(MandateRequired::class);

        $this->declare($harness);
    }

    public function testTheMandateCheckIsOnByDefault(): void
    {
        $command = new DeclareSalary(Harness::tenant(), ExternalReference::fromString('declaration-1'), DraftFixtures::valid());

        static::assertFalse($command->skipMandateCheck);
    }

    public function testTheCommandCanSkipTheMandateCheck(): void
    {
        $harness = new Harness();
        $harness->gateway->willDeclare(Harness::result(1));

        $submission = $this->declareSkippingTheMandate($harness);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
    }

    public function testAThrottledCallLeavesTheRecordPendingAndThrows(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);
        $harness->gateway->willDeclare(UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, 3, ApiErrors::none()));

        try {
            $this->declare($harness);
            static::fail('Expected RetryableUrssafFailure.');
        } catch (RetryableUrssafFailure $retry) {
            static::assertSame(3, $retry->retryAfterSeconds());
        }

        static::assertSame(
            DeclarationState::Pending,
            $harness->declarations->inner()->get(DeclarationRecordId::fromString('id-1'))->state(),
        );

        $harness->gateway->willDeclare(Harness::result(1));
        $again = $this->declare($harness);

        static::assertSame(ItemOutcome::Declared, $again->outcome);
        static::assertSame('id-1', $again->declaration->recordId);
        static::assertSame(2, $again->declaration->status->attempts);
    }

    public function testAPendingRecordIsSentWithoutBeingRecordedAgain(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);
        $harness->record(1, DeclarationState::Pending);
        $harness->gateway->willDeclare(Harness::result(1));

        $submission = $this->declare($harness);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('record-1', $submission->declaration->recordId);
        static::assertSame(
            ['claim:record-1', 'call:050', 'begin', 'save:record-1', 'commit', 'publish:DeclarationDeclared'],
            $harness->journal->entries(),
        );
    }

    public function testAPendingRecordGoesThroughTheMandateGuard(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);

        try {
            $this->declare($harness);
            static::fail('Expected MandateRequired.');
        } catch (MandateRequired $refusal) {
            static::assertSame('mandate.required', $refusal->violationCode());
        }

        $this->assertNoCall($harness);
        static::assertSame([], $harness->journal->entries());
    }

    #[DataProvider('provideStates')]
    public function testAnotherContentIsRefusedWhateverTheStateExceptRejected(DeclarationState $state): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);
        $harness->record(1, $state);

        try {
            $this->declareDraft($harness, self::otherDraft());
            static::fail('Expected DeclarationAlreadyRecorded.');
        } catch (DeclarationAlreadyRecorded $refusal) {
            static::assertSame(
                ['external_reference' => 'declaration-1', 'declaration_record_id' => 'record-1'],
                $refusal->params(),
            );
        }

        $this->assertNoCall($harness);
        static::assertSame([], $harness->journal->entries());
        static::assertSame($state, $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'))->state());
    }

    /**
     * @return iterable<string, array{DeclarationState}>
     */
    public static function provideStates(): iterable
    {
        yield 'pending' => [DeclarationState::Pending];
        yield 'submitting' => [DeclarationState::Submitting];
        yield 'declared' => [DeclarationState::Declared];
        yield 'already declared' => [DeclarationState::AlreadyDeclared];
        yield 'outcome unknown' => [DeclarationState::OutcomeUnknown];
        yield 'invalid credentials' => [DeclarationState::InvalidCredentials];
        yield 'error' => [DeclarationState::Error];
    }

    public function testASubmittingRecordIsNotClaimedAndNeedsNoMandate(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Submitting);

        $submission = $this->declare($harness);

        static::assertSame(ItemOutcome::NotClaimed, $submission->outcome);
        static::assertSame('submitting', $submission->declaration->status->state);
        $this->assertNoCall($harness);
        static::assertSame([], $harness->journal->entries());
    }

    #[DataProvider('provideReplayedStates')]
    public function testAFinalOrUnknownOutcomeIsReplayedWithoutAnyCall(DeclarationState $state): void
    {
        $harness = new Harness();
        $harness->record(1, $state);

        $submission = $this->declare($harness);

        static::assertSame(ItemOutcome::Replayed, $submission->outcome);
        static::assertSame($state->value, $submission->declaration->status->state);
        static::assertSame('record-1', $submission->declaration->recordId);
        $this->assertNoCall($harness);
        static::assertSame([], $harness->journal->entries());
    }

    /**
     * @return iterable<string, array{DeclarationState}>
     */
    public static function provideReplayedStates(): iterable
    {
        yield 'declared' => [DeclarationState::Declared];
        yield 'already declared' => [DeclarationState::AlreadyDeclared];
        yield 'outcome unknown' => [DeclarationState::OutcomeUnknown];
        yield 'invalid credentials' => [DeclarationState::InvalidCredentials];
    }

    public function testAFailedRecordWithAttemptsLeftIsRequeuedThenSent(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);
        $harness->record(1, DeclarationState::Error, 4);
        $harness->gateway->willDeclare(Harness::result(1));

        $submission = $this->declare($harness);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame(5, $submission->declaration->status->attempts);
        static::assertSame(
            [
                'begin',
                'save:record-1',
                'commit',
                'publish:DeclarationRequeued',
                'claim:record-1',
                'call:050',
                'begin',
                'save:record-1',
                'commit',
                'publish:DeclarationDeclared',
            ],
            $harness->journal->entries(),
        );
    }

    public function testAFailedRecordWithAttemptsLeftNeedsTheMandate(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Error, 1);

        try {
            $this->declare($harness);
            static::fail('Expected MandateRequired.');
        } catch (MandateRequired $refusal) {
            static::assertSame('mandate.required', $refusal->violationCode());
        }

        static::assertSame([], $harness->journal->entries());
        static::assertSame(DeclarationState::Error, $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'))->state());
    }

    public function testAnExhaustedRecordIsReturnedWithoutAnyCall(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Error, 5);

        $submission = $this->declare($harness);

        static::assertSame(ItemOutcome::Exhausted, $submission->outcome);
        static::assertSame('error', $submission->declaration->status->state);
        $this->assertNoCall($harness);
        static::assertSame([], $harness->journal->entries());
    }

    public function testRaisingTheMaximumMakesAnExhaustedRecordRetryableAgain(): void
    {
        $harness = new Harness(DeclarationPolicies::maxAttempts(6));
        $harness->mandate(1, MandateState::Confirmed);
        $harness->record(1, DeclarationState::Error, 5);
        $harness->gateway->willDeclare(Harness::result(1));

        $submission = $this->declare($harness);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame(6, $submission->declaration->status->attempts);
    }

    #[DataProvider('provideSupersededContents')]
    public function testARejectedRecordIsSupersededWhateverTheContent(string $content): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);
        $harness->record(1, DeclarationState::Rejected);
        $harness->gateway->willDeclare(Harness::result(1));

        $submission = $this->declareDraft($harness, $content === 'same' ? DraftFixtures::valid() : self::otherDraft());

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('id-1', $submission->declaration->recordId);
        static::assertSame(
            [
                'begin',
                'delete:record-1',
                'save:id-1',
                'commit',
                'publish:DeclarationRecorded',
                'claim:id-1',
                'call:050',
                'begin',
                'save:id-1',
                'commit',
                'publish:DeclarationDeclared',
            ],
            $harness->journal->entries(),
        );
        $ids = array_map(
            static fn(DeclarationRecord $record): string => $record->id()->value(),
            $harness->declarations->inner()->all(),
        );
        static::assertSame(['id-1'], $ids);
        static::assertSame(
            'id-1',
            $harness->declarations->findByExternalReference(Harness::tenant(), ExternalReference::fromString('declaration-1'))?->id()->value(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSupersededContents(): iterable
    {
        yield 'same content' => ['same'];
        yield 'other content' => ['other'];
    }

    public function testARejectedRecordIsKeptWhenTheMandateIsMissing(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Rejected);

        try {
            $this->declare($harness);
            static::fail('Expected MandateRequired.');
        } catch (MandateRequired $refusal) {
            static::assertSame('mandate.required', $refusal->violationCode());
        }

        static::assertSame([], $harness->journal->entries());
        static::assertSame(DeclarationState::Rejected, $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'))->state());
    }

    public function testTheRecordOfAnotherTenantIsNotAConflict(): void
    {
        $harness = new Harness();
        $harness->mandate(1, MandateState::Confirmed);
        $harness->record(1, DeclarationState::Declared, 1, Harness::OTHER_TENANT);
        $harness->gateway->willDeclare(Harness::result(1));

        $submission = $this->declareDraft($harness, self::otherDraft());

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame('id-1', $submission->declaration->recordId);
    }

    private static function draftStartingToday(): DeclarationDraft
    {
        return DraftFixtures::withParts(
            new EmploymentPeriodDraft('2026-11-15', '2026-11-15'),
            new HourlyIncomeDraft('12.5', 10, 0, 0, 0, [], []),
            null,
            null,
            null,
        );
    }

    private static function otherDraft(): DeclarationDraft
    {
        return DraftFixtures::withScalars(null, null, '2026-11-02', null, null);
    }

    private function declare(Harness $harness): DeclarationSubmission
    {
        return $this->handle($harness, self::command('declaration-1', DraftFixtures::valid(), false));
    }

    private function declareDraft(Harness $harness, DeclarationDraft $draft): DeclarationSubmission
    {
        return $this->handle($harness, self::command('declaration-1', $draft, false));
    }

    private function declareSkippingTheMandate(Harness $harness): DeclarationSubmission
    {
        return $this->handle($harness, self::command('declaration-1', DraftFixtures::valid(), true));
    }

    private static function command(string $reference, DeclarationDraft $draft, bool $skip): DeclareSalary
    {
        return new DeclareSalary(Harness::tenant(), ExternalReference::fromString($reference), $draft, $skip);
    }

    private function handle(Harness $harness, DeclareSalary $command): DeclarationSubmission
    {
        $guard = new MandateGuard($harness->mandates, $harness->accounts, $harness->policy);
        $submitter = new DeclarationSubmitter($harness->declarations, $harness->gateway, $harness->clock, $harness->unitOfWork, $harness->policy);

        return (new DeclareSalaryHandler(
            $harness->declarations,
            $submitter,
            $guard,
            $harness->ids,
            $harness->clock,
            $harness->unitOfWork,
            $harness->policy,
        ))($command);
    }

    private function assertNothingHappened(Harness $harness): void
    {
        static::assertSame([], $harness->journal->entries());
        static::assertSame([], $harness->declarations->inner()->all());
        $this->assertNoCall($harness);
    }

    private function assertNoCall(Harness $harness): void
    {
        static::assertSame([], $harness->gateway->calls());
    }
}
