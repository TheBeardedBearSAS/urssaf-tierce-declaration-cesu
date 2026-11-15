<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

final class SelectorsTest extends TestCase
{
    public function testAnEmployerIsDesignatedByAccountIdReferenceOrCesuNumber(): void
    {
        $accountId = EmployerAccountId::fromString('account-1');
        $reference = ExternalReference::fromString('employer-1');
        $cesu = CesuNumber::fromString('Z0003752390009');

        static::assertSame($accountId, EmployerSelector::byAccountId($accountId)->reference);
        static::assertSame($reference, EmployerSelector::byExternalReference($reference)->reference);
        static::assertSame($cesu, EmployerSelector::byCesuNumber($cesu)->reference);
    }

    public function testADeclarationIsDesignatedByRecordIdReferenceOrDeclarationId(): void
    {
        $recordId = DeclarationRecordId::fromString('record-1');
        $reference = ExternalReference::fromString('declaration-1');
        $declarationId = DeclarationId::fromString('209299542888');

        static::assertSame($recordId, DeclarationSelector::byRecordId($recordId)->reference);
        static::assertSame($reference, DeclarationSelector::byExternalReference($reference)->reference);
        static::assertSame($declarationId, DeclarationSelector::byDeclarationId($declarationId)->reference);
    }
}
