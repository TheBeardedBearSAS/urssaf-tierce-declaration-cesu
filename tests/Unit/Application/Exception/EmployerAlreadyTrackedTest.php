<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Exception;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

final class EmployerAlreadyTrackedTest extends TestCase
{
    public function testWithExternalReference(): void
    {
        $exception = EmployerAlreadyTracked::withExternalReference(
            ExternalReference::fromString('employer-1'),
            EmployerAccountId::fromString('account-1'),
        );

        static::assertSame('employer_account.already_tracked', $exception->violationCode());
        static::assertSame(['external_reference' => 'employer-1', 'employer_account_id' => 'account-1'], $exception->params());
        static::assertSame('The external reference "employer-1" is already used by another employer account.', $exception->getMessage());
    }

    public function testWithCesuNumber(): void
    {
        $exception = EmployerAlreadyTracked::withCesuNumber(
            CesuNumber::fromString('Z0003752390009'),
            EmployerAccountId::fromString('account-1'),
        );

        static::assertSame('employer_account.already_tracked', $exception->violationCode());
        static::assertSame(['cesu_number' => 'Z0003752390009', 'employer_account_id' => 'account-1'], $exception->params());
        static::assertSame('The Cesu number "Z0003752390009" is already tracked by another employer account.', $exception->getMessage());
    }
}
