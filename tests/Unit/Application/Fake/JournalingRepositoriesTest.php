<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

final class JournalingRepositoriesTest extends TestCase
{
    public function testTheAccountsJournalTheirWritesAndDelegateTheirReads(): void
    {
        $harness = new Harness();
        $account = $harness->account(1, EmployerStage::MandateNotified);
        $other = $harness->account(2, EmployerStage::Registered, Harness::OTHER_TENANT);
        $tenant = Harness::tenant();

        static::assertSame($account->id()->value(), $harness->accounts->findByCesuNumber($tenant, $account->cesuNumber())?->id()->value());
        static::assertSame($account->id()->value(), $harness->accounts->findByExternalReference($tenant, $account->externalReference())?->id()->value());
        static::assertCount(1, $harness->accounts->findLeastRecentlyChecked($tenant, 10));
        static::assertSame(['tenant-1', 'tenant-2'], array_map(static fn(TenantId $t): string => $t->value(), $harness->accounts->tenants()));
        static::assertSame([], $harness->journal->entries());

        $harness->accounts->save($account);
        $harness->accounts->delete($other->id());

        static::assertSame(['save:account-1', 'delete:account-2'], $harness->journal->entries());
        static::assertCount(1, $harness->accounts->inner()->all());
    }

    public function testTheMandatesJournalTheirWritesAndDelegateTheirReads(): void
    {
        $harness = new Harness();
        $mandate = $harness->mandate(1, MandateState::Confirmed);
        $tenant = Harness::tenant();

        static::assertSame('mandate-1', $harness->mandates->findActive($tenant, $mandate->cesuNumber())?->id()->value());
        static::assertCount(1, $harness->mandates->findByEmployer($tenant, $mandate->cesuNumber()));
        static::assertSame([], $harness->journal->entries());

        $harness->mandates->save($mandate);

        static::assertSame(['save:mandate-1'], $harness->journal->entries());
        static::assertCount(1, $harness->mandates->inner()->all());

        $harness->mandates->delete(MandateId::fromString('mandate-1'));

        static::assertSame(['save:mandate-1', 'delete:mandate-1'], $harness->journal->entries());
        static::assertSame([], $harness->mandates->inner()->all());
    }

    public function testTheRecordsJournalTheEmployerErasureWithItsStatesAndDelegateTheCount(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Declared);
        $harness->record(2, DeclarationState::Error);
        $harness->record(3, DeclarationState::Submitting);
        $tenant = Harness::tenant();
        $cesu = Harness::cesuNumber(1);

        static::assertSame(1, $harness->declarations->countByEmployer($tenant, $cesu)['submitting']);
        static::assertSame(1, $harness->declarations->countByEmployer($tenant, $cesu)['declared']);
        static::assertSame([], $harness->journal->entries());

        $deleted = $harness->declarations->deleteByEmployer($tenant, $cesu, [DeclarationState::Declared, DeclarationState::Error]);

        static::assertSame(['record-1', 'record-2'], array_map(static fn(DeclarationRecordId $id): string => $id->value(), $deleted));
        static::assertSame(['delete-by-employer:Z0003752390009:declared,error'], $harness->journal->entries());
        static::assertCount(1, $harness->declarations->inner()->all());
    }

    public function testTheRecordsJournalTheirWritesAndDelegateTheirReads(): void
    {
        $harness = new Harness();
        $pending = $harness->record(1, DeclarationState::Pending);
        $declared = $harness->record(2, DeclarationState::Declared);
        $submitting = $harness->record(3, DeclarationState::Submitting);
        $tenant = Harness::tenant();
        $at = Instant::fromIso8601('2026-11-15T09:30:00Z');

        static::assertSame('record-1', $harness->declarations->findByExternalReference($tenant, $pending->externalReference())?->id()->value());
        static::assertSame('record-2', $harness->declarations->findByDeclarationId($tenant, $declared->declarationId() ?? static::fail('No declaration id.'))?->id()->value());
        static::assertCount(1, $harness->declarations->findByState($tenant, DeclarationState::Declared, 10));
        static::assertCount(1, $harness->declarations->findStaleSubmissions($at, 10));
        static::assertSame(1, $harness->declarations->countByState($tenant)['submitting']);
        static::assertSame(['tenant-1'], array_map(static fn(TenantId $t): string => $t->value(), $harness->declarations->tenantsWithState(DeclarationState::Declared)));
        static::assertSame([], $harness->journal->entries());

        static::assertTrue($harness->declarations->claimForSubmission($pending->id(), $at));
        $harness->declarations->save($submitting);
        $harness->declarations->delete($declared->id());

        static::assertSame(['claim:record-1', 'save:record-3', 'delete:record-2'], $harness->journal->entries());
        static::assertCount(2, $harness->declarations->inner()->all());
    }

    public function testARefusedClaimLosesWithoutAJournalEntry(): void
    {
        $harness = new Harness();
        $pending = $harness->record(1, DeclarationState::Pending);
        $other = $harness->record(2, DeclarationState::Pending);
        $harness->declarations->refuseClaim($pending->id());
        $at = Instant::fromIso8601('2026-11-15T09:30:00Z');

        static::assertFalse($harness->declarations->claimForSubmission($pending->id(), $at));
        static::assertSame(DeclarationState::Pending, $harness->declarations->get($pending->id())->state());
        static::assertTrue($harness->declarations->claimForSubmission($other->id(), $at));
        static::assertFalse($harness->declarations->claimForSubmission($other->id(), $at));
        static::assertSame(['claim:record-2'], $harness->journal->entries());
    }
}
