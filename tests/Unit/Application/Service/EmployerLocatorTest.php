<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;

final class EmployerLocatorTest extends TestCase
{
    public function testLocatesByAccountIdExternalReferenceOrCesuNumber(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->account(2);
        $locator = new EmployerLocator($harness->accounts);

        static::assertSame('account-2', $locator->locate(Harness::tenant(), EmployerSelector::byAccountId(EmployerAccountId::fromString('account-2')))->id()->value());
        static::assertSame('account-1', $locator->locate(Harness::tenant(), EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1')))->id()->value());
        static::assertSame('account-2', $locator->locate(Harness::tenant(), EmployerSelector::byCesuNumber(Harness::cesuNumber(2)))->id()->value());
    }

    public function testUnknownExternalReference(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::Registered, Harness::OTHER_TENANT);
        $locator = new EmployerLocator($harness->accounts);

        $exception = self::notFound(static fn(): EmployerAccount => $locator->locate(Harness::tenant(), EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1'))));

        static::assertSame('employer_account.not_found', $exception->violationCode());
        static::assertSame('employer-1', $exception->identifier());
        static::assertSame('employer_account', $exception->recordType());
    }

    public function testUnknownCesuNumber(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::Registered, Harness::OTHER_TENANT);
        $locator = new EmployerLocator($harness->accounts);

        $exception = self::notFound(static fn(): EmployerAccount => $locator->locate(Harness::tenant(), EmployerSelector::byCesuNumber(Harness::cesuNumber(1))));

        static::assertSame('employer_account.not_found', $exception->violationCode());
        static::assertSame(Harness::cesuNumber(1)->value(), $exception->identifier());
    }

    public function testAccountOfAnotherTenant(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::Registered, Harness::OTHER_TENANT);
        $locator = new EmployerLocator($harness->accounts);

        $exception = self::notFound(static fn(): EmployerAccount => $locator->locate(Harness::tenant(), EmployerSelector::byAccountId(EmployerAccountId::fromString('account-1'))));

        static::assertSame('employer_account.not_found', $exception->violationCode());
        static::assertSame('account-1', $exception->identifier());
    }

    /**
     * @param callable(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    private static function notFound(callable $action): RecordNotFound
    {
        try {
            $action();
        } catch (RecordNotFound $exception) {
            return $exception;
        }
        static::fail('Expected a RecordNotFound exception.');
    }
}
