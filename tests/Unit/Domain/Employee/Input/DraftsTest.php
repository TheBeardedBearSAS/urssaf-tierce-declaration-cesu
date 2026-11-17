<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeIdentityDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;

final class DraftsTest extends TestCase
{
    /**
     * @return iterable<string, array{EmployeeIdentityDraft, bool}>
     */
    public static function provideIdentityDrafts(): iterable
    {
        yield 'all null' => [new EmployeeIdentityDraft(null, null, null, null, null, null, null), false];
        yield 'all empty' => [new EmployeeIdentityDraft('', '', '', '', '', '', ''), false];
        yield 'birth name' => [new EmployeeIdentityDraft('a', null, null, null, null, null, null), true];
        yield 'usage name' => [new EmployeeIdentityDraft(null, 'a', null, null, null, null, null), true];
        yield 'first name' => [new EmployeeIdentityDraft(null, null, 'a', null, null, null, null), true];
        yield 'birth date' => [new EmployeeIdentityDraft(null, null, null, 'a', null, null, null), true];
        yield 'birth city' => [new EmployeeIdentityDraft(null, null, null, null, 'a', null, null), true];
        yield 'birth department' => [new EmployeeIdentityDraft(null, null, null, null, null, 'a', null), true];
        yield 'birth country' => [new EmployeeIdentityDraft(null, null, null, null, null, null, 'a'), true];
        yield 'last field filled after empty ones' => [new EmployeeIdentityDraft('', '', '', '', '', '', '100'), true];
    }

    #[DataProvider('provideIdentityDrafts')]
    public function testIdentityDraftIsFilled(EmployeeIdentityDraft $draft, bool $expected): void
    {
        static::assertSame($expected, $draft->isFilled());
    }

    public function testFilledIdentity(): void
    {
        $filled = new EmployeeIdentityDraft('a', null, null, null, null, null, null);

        static::assertNull((new EmployeeReferenceDraft(null, null, null))->filledIdentity());
        static::assertNull((new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('', null, null, null, null, null, null)))->filledIdentity());
        static::assertSame($filled, (new EmployeeReferenceDraft(null, null, $filled))->filledIdentity());
    }
}
