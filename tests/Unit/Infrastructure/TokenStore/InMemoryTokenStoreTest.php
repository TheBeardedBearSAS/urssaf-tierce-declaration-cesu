<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\TokenStore;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\AccessToken;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\TokenStore\InMemoryTokenStore;

final class InMemoryTokenStoreTest extends TestCase
{
    public function testMissReturnsNull(): void
    {
        static::assertNull((new InMemoryTokenStore())->get('tenant-a'));
    }

    public function testSavedTokenIsReturnedPerKey(): void
    {
        $store = new InMemoryTokenStore();
        $tokenA = self::token('token-a');
        $tokenB = self::token('token-b');

        $store->save('tenant-a', $tokenA);
        $store->save('tenant-b', $tokenB);

        static::assertSame($tokenA, $store->get('tenant-a'));
        static::assertSame($tokenB, $store->get('tenant-b'));
    }

    public function testSaveReplacesThePreviousToken(): void
    {
        $store = new InMemoryTokenStore();
        $renewed = self::token('renewed');

        $store->save('tenant-a', self::token('initial'));
        $store->save('tenant-a', $renewed);

        static::assertSame($renewed, $store->get('tenant-a'));
    }

    public function testDeleteRemovesTheToken(): void
    {
        $store = new InMemoryTokenStore();
        $store->save('tenant-a', self::token('token-a'));

        $store->delete('tenant-a');

        static::assertNull($store->get('tenant-a'));
    }

    public function testDeleteOfAMissingKeyIsANoOp(): void
    {
        $store = new InMemoryTokenStore();

        $store->delete('missing');

        static::assertNull($store->get('missing'));
    }

    private static function token(string $value): AccessToken
    {
        return AccessToken::of($value, new DateTimeImmutable('2100-01-01T00:00:00+00:00'));
    }
}
