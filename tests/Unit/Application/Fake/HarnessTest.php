<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;

/**
 * The harness builds every aggregate in every state, stored without journal entry nor pending event.
 *
 * @mago-expect lint:too-many-methods
 */
final class HarnessTest extends TestCase
{
    public function testTheCesuNumbersAreDerivedFromTheNumber(): void
    {
        static::assertSame('Z0003752390009', Harness::cesuNumber(1)->value());
        static::assertSame('Y0000000000002', Harness::cesuNumber(2)->value());
        static::assertSame('Y0000000000002', Harness::employer(2)->cesuNumber()->value());
        static::assertSame('1975-01-30', Harness::employer(2)->birthDate()->toString());
    }

    /**
     * @return iterable<string, array{EmployerStage}>
     */
    public static function stages(): iterable
    {
        foreach (EmployerStage::cases() as $stage) {
            yield $stage->value => [$stage];
        }
    }

    #[DataProvider('stages')]
    public function testAnAccountIsBuiltAtEveryStage(EmployerStage $stage): void
    {
        $harness = new Harness();

        $account = $harness->account(2, $stage, Harness::OTHER_TENANT);

        static::assertSame($stage, $account->stage());
        static::assertSame('account-2', $account->id()->value());
        static::assertSame('employer-2', $account->externalReference()->value());
        static::assertSame(Harness::OTHER_TENANT, $account->tenantId()->value());
        static::assertSame([], $account->releaseEvents());
        static::assertSame([], $harness->journal->entries());
        static::assertSame($stage, $harness->accounts->get($account->id())->stage());
        static::assertSame([], $harness->accounts->get($account->id())->releaseEvents());
    }

    public function testAnAccountDefaultsToTheRegisteredStageOfTheDefaultTenant(): void
    {
        $account = (new Harness())->account(1);

        static::assertSame(EmployerStage::Registered, $account->stage());
        static::assertSame(Harness::TENANT, $account->tenantId()->value());
        static::assertSame('Z0003752390009', $account->cesuNumber()->value());
    }

    /**
     * @return iterable<string, array{MandateState}>
     */
    public static function mandateStates(): iterable
    {
        foreach (MandateState::cases() as $state) {
            yield $state->value => [$state];
        }
    }

    #[DataProvider('mandateStates')]
    public function testAMandateIsBuiltInEveryState(MandateState $state): void
    {
        $harness = new Harness();

        $mandate = $harness->mandate(3, $state, Harness::OTHER_TENANT);

        static::assertSame($state, $mandate->state());
        static::assertSame('mandate-3', $mandate->id()->value());
        static::assertSame('employer-3', $mandate->externalReference()->value());
        static::assertSame('Y0000000000003', $mandate->cesuNumber()->value());
        static::assertSame(Harness::OTHER_TENANT, $mandate->tenantId()->value());
        static::assertSame([], $mandate->releaseEvents());
        static::assertSame([], $harness->journal->entries());
        static::assertSame($state, $harness->mandates->get($mandate->id())->state());
    }

    public function testAMandateDefaultsToNotified(): void
    {
        $mandate = (new Harness())->mandate(1);

        static::assertSame(MandateState::Notified, $mandate->state());
        static::assertSame(Harness::TENANT, $mandate->tenantId()->value());
    }

    public function testTheDeclarationIsBuiltWithThePolicyOfTheHarness(): void
    {
        $declaration = (new Harness())->declaration();

        static::assertSame('Z0003752390009', $declaration->content()->employerCesuNumber()->value());
        static::assertSame(Harness::NOW, $this->nowOf(new Harness()));
    }

    /**
     * @return iterable<string, array{DeclarationState}>
     */
    public static function declarationStates(): iterable
    {
        foreach (DeclarationState::cases() as $state) {
            yield $state->value => [$state];
        }
    }

    #[DataProvider('declarationStates')]
    public function testARecordIsBuiltInEveryState(DeclarationState $state): void
    {
        $harness = new Harness();

        $record = $harness->record(4, $state);

        static::assertSame($state, $record->state());
        static::assertSame('record-4', $record->id()->value());
        static::assertSame('declaration-4', $record->externalReference()->value());
        static::assertSame(Harness::TENANT, $record->tenantId()->value());
        static::assertSame(
            $harness->declaration()->fingerprint(),
            $record->snapshot()->fingerprint()->value(),
        );
        static::assertSame($state === DeclarationState::Pending ? 0 : 1, $record->attempts());
        static::assertSame([], $record->releaseEvents());
        static::assertSame([], $harness->journal->entries());
        static::assertSame($state, $harness->declarations->get($record->id())->state());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function attempts(): iterable
    {
        yield 'one' => [1];
        yield 'two' => [2];
        yield 'many' => [7];
    }

    #[DataProvider('attempts')]
    public function testARecordInErrorKeepsItsAttempts(int $attempts): void
    {
        $record = (new Harness())->record(5, DeclarationState::Error, $attempts, Harness::OTHER_TENANT);

        static::assertSame(DeclarationState::Error, $record->state());
        static::assertSame($attempts, $record->attempts());
        static::assertSame(Harness::OTHER_TENANT, $record->tenantId()->value());
        static::assertSame('2026-11-15T09:00:00Z', $record->lastAttemptAt()?->toIso8601());
    }

    public function testTheDeclaredRecordHoldsTheSampleResult(): void
    {
        $record = (new Harness())->record(6, DeclarationState::Declared);

        static::assertSame('209299500006', $record->declarationId()?->value());
        static::assertSame('209299500007', (new Harness())->record(7, DeclarationState::Declared)->declarationId()?->value());
    }

    public function testTwoDeclaredRecordsOfATenantCanBeStored(): void
    {
        $harness = new Harness();

        $harness->record(1, DeclarationState::Declared);
        $harness->record(2, DeclarationState::Declared);

        static::assertCount(2, $harness->declarations->inner()->all());
    }

    public function testTheHarnessUsesTheGivenPolicy(): void
    {
        $policy = new WorkflowPolicy(new DateTimeZone('UTC'), new DateInterval('PT1M'), 2, true, true, 5, 10);

        static::assertSame($policy, (new Harness($policy))->policy);
    }

    public function testTheCollaboratorsShareTheJournal(): void
    {
        $harness = new Harness();

        $harness->unitOfWork->run(static fn(RecordedEvents $events): int => \count($events->all()));
        $harness->gateway->willRegisterMandate();
        $harness->gateway->registerMandate(Harness::tenant(), Harness::employer(1));
        $harness->accounts->save($harness->account(1));
        $harness->mandates->save($harness->mandate(1));
        $harness->declarations->save($harness->record(1, DeclarationState::Pending));

        static::assertSame(
            ['begin', 'commit', 'call:030', 'save:account-1', 'save:mandate-1', 'save:record-1'],
            $harness->journal->entries(),
        );
        static::assertSame('id-1', $harness->ids->generate());
        static::assertSame(Harness::at(Harness::NOW)->toIso8601(), $harness->clock->now()->format('Y-m-d\TH:i:s\Z'));
    }

    private function nowOf(Harness $harness): string
    {
        return $harness->clock->now()->format('Y-m-d\TH:i:s\Z');
    }
}
