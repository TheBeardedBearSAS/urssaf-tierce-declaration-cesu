<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;

/**
 * Proves the contract itself: the in-memory outbox satisfies the same scenarios as the Doctrine one.
 */
final class InMemoryOutboxContractTest extends TestCase
{
    use OutboxContract;

    protected function createOutbox(): Outbox
    {
        return new InMemoryOutbox();
    }
}
