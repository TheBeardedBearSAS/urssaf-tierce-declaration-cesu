<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\SpecVersion;

final class SpecVersionTest extends TestCase
{
    public function testItExposesTheImplementedSpecificationVersion(): void
    {
        static::assertMatchesRegularExpression('/^2\.0\.2$/', SpecVersion::CURRENT);
    }
}
