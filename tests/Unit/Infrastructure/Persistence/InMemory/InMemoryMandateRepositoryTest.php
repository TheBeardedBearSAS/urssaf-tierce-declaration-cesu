<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\ContractSamples;

/**
 * What is specific to the in-memory repository (snapshots, orders on ties); the behaviour shared with the other
 * adapters is in the repository contract.
 */
final class InMemoryMandateRepositoryTest extends TestCase
{
    private InMemoryMandateRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryMandateRepository();
    }

    public function testEveryReadReturnsANewCopy(): void
    {
        $mandate = ContractSamples::mandate(1);
        $this->repository->save($mandate);

        $first = $this->repository->get($mandate->id());

        static::assertNotSame($mandate, $first);
        static::assertNotSame($first, $this->repository->get($mandate->id()));
        static::assertNotSame($first, $this->repository->findActive($mandate->tenantId(), $mandate->cesuNumber()));
        static::assertNotSame($first, $this->repository->findByEmployer($mandate->tenantId(), $mandate->cesuNumber())[0]);
    }

    public function testAChangeIsOnlyVisibleOnceSaved(): void
    {
        $mandate = ContractSamples::mandate(1);
        $this->repository->save($mandate);
        $mandate->cancel(ContractSamples::at('2026-11-11T09:00:00Z'));

        static::assertSame(MandateState::Notified, $this->repository->get($mandate->id())->state());

        $this->repository->save($mandate);

        static::assertSame(MandateState::Cancelled, $this->repository->get($mandate->id())->state());
    }

    public function testAllReturnsCopiesInInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::mandate(2));
        $this->repository->save(ContractSamples::mandate(1));

        $all = $this->repository->all();

        static::assertSame(['mandate-2', 'mandate-1'], array_map(static fn(Mandate $m): string => $m->id()->value(), $all));
        $all[0]->cancel(ContractSamples::at('2026-11-11T09:00:00Z'));
        static::assertSame(MandateState::Notified, $this->repository->get(MandateId::fromString('mandate-2'))->state());
    }

    public function testTheMandatesNotifiedAtTheSameInstantKeepTheirInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 3, MandateState::Cancelled));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Cancelled));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Notified));

        static::assertSame(
            ['mandate-3', 'mandate-1', 'mandate-2'],
            array_map(
                static fn(Mandate $m): string => $m->id()->value(),
                $this->repository->findByEmployer(ContractSamples::tenant(), ContractSamples::cesuNumber(1)),
            ),
        );
    }

    public function testACancelledMandateCanBeSavedWhileAnotherIsActive(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Notified));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Cancelled));

        static::assertCount(2, $this->repository->all());
    }
}
