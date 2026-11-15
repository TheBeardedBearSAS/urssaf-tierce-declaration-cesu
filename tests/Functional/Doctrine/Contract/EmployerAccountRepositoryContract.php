<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\RegistrationOrigin;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;

/**
 * Behaviour every {@see EmployerAccountRepository} must have, independently of the storage. Identity-related behaviour
 * (copies versus managed instances) is deliberately not part of the contract. One method per scenario.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 * @mago-expect lint:too-many-methods
 */
trait EmployerAccountRepositoryContract
{
    protected EmployerAccountRepository $repository;

    abstract protected function createRepository(): EmployerAccountRepository;

    protected function setUp(): void
    {
        $this->repository = $this->createRepository();
    }

    /**
     * @param list<EmployerAccount> $accounts
     *
     * @return list<string>
     */
    protected static function accountIds(array $accounts): array
    {
        return array_map(static fn(EmployerAccount $account): string => $account->id()->value(), $accounts);
    }

    public function testASavedAccountIsReadBack(): void
    {
        $this->repository->save(ContractSamples::account(1, EmployerStage::MandateNotified, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));

        $read = $this->repository->get(EmployerAccountId::fromString('account-1'));

        static::assertSame('account-1', $read->id()->value());
        static::assertSame('tenant-1', $read->tenantId()->value());
        static::assertSame('employer-1', $read->externalReference()->value());
        static::assertTrue($read->employer()->equals(ContractSamples::employer(1)));
        static::assertSame(RegistrationOrigin::Registered, $read->origin());
        static::assertSame(EmployerStage::MandateNotified, $read->stage());
        static::assertSame('2026-11-10T09:00:00Z', $read->createdAt()->toIso8601());
        static::assertSame('2026-11-10T09:00:00Z', $read->updatedAt()->toIso8601());
        static::assertNull($read->lastStatus());
        static::assertNull($read->lastStatusEmployeeKind());
        static::assertNull($read->lastStatusEmployeeCesuNumber());
        static::assertNull($read->lastCheckedAt());
    }

    public function testAnImportedOriginIsReadBack(): void
    {
        $account = EmployerAccount::import(
            EmployerAccountId::fromString('account-1'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-1'),
            ContractSamples::employer(1),
            ContractSamples::at(),
        );
        $this->repository->save($account);

        static::assertSame(RegistrationOrigin::Imported, $this->repository->get($account->id())->origin());
    }

    public function testTheEventsAreNeitherPersistedNorConsumedFromTheCaller(): void
    {
        $account = ContractSamples::pendingAccount(1);

        $this->repository->save($account);

        static::assertCount(1, $account->releaseEvents());
        static::assertSame([], $this->repository->get($account->id())->releaseEvents());
    }

    public function testASavedChangeIsReadBack(): void
    {
        $account = ContractSamples::account(1);
        $this->repository->save($account);
        $status = new EmployerStatus(true, 'actif', 'inconnu-demain', true, 'actif', 'Motif', true);
        $checkedAt = ContractSamples::at('2026-11-12T08:30:00Z');
        $stored = $this->repository->get($account->id());
        $stored->applyStatus($status, EmployeeByCesuNumber::of(EmployeeSamples::cesuNumber()), $checkedAt);

        $this->repository->save($stored);
        $read = $this->repository->get($account->id());

        static::assertSame(EmployerStage::AiciActive, $read->stage());
        $lastStatus = $read->lastStatus();
        static::assertInstanceOf(EmployerStatus::class, $lastStatus);
        static::assertTrue($lastStatus->equals($status));
        static::assertSame('inconnu-demain', $lastStatus->rawSepaMandateState());
        static::assertSame(EmployeeReferenceKind::CesuNumber, $read->lastStatusEmployeeKind());
        static::assertSame(EmployeeSamples::cesuNumber()->value(), $read->lastStatusEmployeeCesuNumber()?->value());
        static::assertSame('2026-11-12T08:30:00Z', $read->lastCheckedAt()?->toIso8601());
        static::assertSame('2026-11-12T08:30:00Z', $read->updatedAt()->toIso8601());
        static::assertSame('2026-11-10T09:00:00Z', $read->createdAt()->toIso8601());
    }

    public function testAStatusWithoutEmployeeIsReadBack(): void
    {
        $account = ContractSamples::account(1);
        $account->applyStatus(new EmployerStatus(false, null, null, false, null, null, false), null, ContractSamples::at('2026-11-12T08:30:00Z'));
        $this->repository->save($account);

        $read = $this->repository->get($account->id());

        $lastStatus = $read->lastStatus();
        static::assertInstanceOf(EmployerStatus::class, $lastStatus);
        static::assertFalse($lastStatus->registered());
        static::assertNull($lastStatus->rawOnlineAccountState());
        static::assertNull($read->lastStatusEmployeeKind());
        static::assertNull($read->lastStatusEmployeeCesuNumber());
    }

    public function testAnUnknownAccountIsNotFound(): void
    {
        try {
            $this->repository->get(EmployerAccountId::fromString('missing'));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('employer_account.not_found', $exception->violationCode());
            static::assertSame('missing', $exception->identifier());
        }
    }

    public function testTheSameCesuNumberOfAnotherAccountOfTheTenantIsRefused(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $duplicate = EmployerAccount::register(
            EmployerAccountId::fromString('account-2'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-2'),
            ContractSamples::employer(1),
            ContractSamples::at(),
        );

        try {
            $this->repository->save($duplicate);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.duplicate_cesu_number', $exception->violationCode());
            static::assertSame(['cesu_number' => ContractSamples::cesuNumber(1)->value()], $exception->params());
        }
    }

    public function testTheSameExternalReferenceOfAnotherAccountOfTheTenantIsRefused(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $duplicate = EmployerAccount::register(
            EmployerAccountId::fromString('account-2'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-1'),
            ContractSamples::employer(2),
            ContractSamples::at(),
        );

        try {
            $this->repository->save($duplicate);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.duplicate_external_reference', $exception->violationCode());
            static::assertSame(['external_reference' => 'employer-1'], $exception->params());
        }
    }

    public function testTheSameCesuNumberAndExternalReferenceInAnotherTenantAreAccepted(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $this->repository->save(ContractSamples::accountOfEmployer(1, 2, EmployerStage::Registered, ContractSamples::OTHER_TENANT));

        static::assertSame(
            'tenant-2',
            $this->repository->findByCesuNumber(ContractSamples::tenant(ContractSamples::OTHER_TENANT), ContractSamples::cesuNumber(1))?->tenantId()->value(),
        );
    }

    public function testSavingAnExistingAccountAgainIsAnUpdateNotADuplicate(): void
    {
        $account = ContractSamples::account(1);
        $this->repository->save($account);
        $this->repository->save($account);

        static::assertSame(['account-1'], self::accountIds($this->repository->findLeastRecentlyChecked(ContractSamples::tenant(), 10)));
    }

    public function testTheFindersAreTenantScopedAndReturnNullWhenAbsent(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $tenant = ContractSamples::tenant();
        $other = ContractSamples::tenant(ContractSamples::OTHER_TENANT);

        static::assertSame('account-1', $this->repository->findByCesuNumber($tenant, ContractSamples::cesuNumber(1))?->id()->value());
        static::assertSame('account-1', $this->repository->findByExternalReference($tenant, ExternalReference::fromString('employer-1'))?->id()->value());
        static::assertNull($this->repository->findByCesuNumber($other, ContractSamples::cesuNumber(1)));
        static::assertNull($this->repository->findByExternalReference($other, ExternalReference::fromString('employer-1')));
        static::assertNull($this->repository->findByCesuNumber($tenant, ContractSamples::cesuNumber(2)));
        static::assertNull($this->repository->findByExternalReference($tenant, ExternalReference::fromString('employer-2')));
    }

    public function testTheLeastRecentlyCheckedComeNeverCheckedFirstThenAscending(): void
    {
        $this->repository->save(ContractSamples::checkedAccount(1, '2026-11-12T10:00:00Z'));
        $this->repository->save(ContractSamples::checkedAccount(2, '2026-11-11T10:00:00Z'));
        $this->repository->save(ContractSamples::account(3, EmployerStage::Registered, ContractSamples::TENANT, ContractSamples::AT));
        $this->repository->save(ContractSamples::account(4, EmployerStage::Registered, ContractSamples::OTHER_TENANT));
        $tenant = ContractSamples::tenant();

        static::assertSame(['account-3', 'account-2', 'account-1'], self::accountIds($this->repository->findLeastRecentlyChecked($tenant, 10)));
        static::assertSame(['account-3', 'account-2'], self::accountIds($this->repository->findLeastRecentlyChecked($tenant, 2)));
        static::assertSame(['account-4'], self::accountIds($this->repository->findLeastRecentlyChecked(ContractSamples::tenant(ContractSamples::OTHER_TENANT), 10)));
        static::assertSame([], $this->repository->findLeastRecentlyChecked(ContractSamples::tenant('tenant-9'), 10));
    }

    public function testTheTenantsAreDistinctAndSorted(): void
    {
        static::assertSame([], $this->repository->tenants());

        $this->repository->save(ContractSamples::account(1, EmployerStage::Registered, 'tenant-b'));
        $this->repository->save(ContractSamples::account(2, EmployerStage::Registered, 'tenant-a'));
        $this->repository->save(ContractSamples::account(3, EmployerStage::Registered, 'tenant-b'));

        static::assertSame(
            ['tenant-a', 'tenant-b'],
            array_map(static fn(TenantId $tenant): string => $tenant->value(), $this->repository->tenants()),
        );
    }

    public function testADeletedAccountIsGoneAndDeletingAMissingOneIsANoOp(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $this->repository->save(ContractSamples::account(2));

        $this->repository->delete(EmployerAccountId::fromString('account-1'));
        $this->repository->delete(EmployerAccountId::fromString('missing'));

        static::assertNull($this->repository->findByExternalReference(ContractSamples::tenant(), ExternalReference::fromString('employer-1')));
        static::assertSame(['account-2'], self::accountIds($this->repository->findLeastRecentlyChecked(ContractSamples::tenant(), 10)));
        $this->expectException(RecordNotFound::class);
        $this->repository->get(EmployerAccountId::fromString('account-1'));
    }
}
