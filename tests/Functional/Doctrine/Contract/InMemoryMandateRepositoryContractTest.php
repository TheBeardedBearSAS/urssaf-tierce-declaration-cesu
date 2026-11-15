<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;

/**
 * Proves the contract itself: the in-memory repository satisfies the same scenarios as the Doctrine one.
 */
final class InMemoryMandateRepositoryContractTest extends TestCase
{
    use MandateRepositoryContract;

    protected function createRepository(): MandateRepository
    {
        return new InMemoryMandateRepository();
    }
}
