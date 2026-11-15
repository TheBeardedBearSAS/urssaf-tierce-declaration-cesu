<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Persistence\InMemory;

final readonly class SelectionItem
{
    public function __construct(
        public string $name,
        public int $rank,
    ) {}
}
