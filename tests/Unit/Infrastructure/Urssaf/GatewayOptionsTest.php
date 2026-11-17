<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\GatewayOptions;

final class GatewayOptionsTest extends TestCase
{
    public function testDefaultsGiveNothing(): void
    {
        $options = GatewayOptions::defaults();

        static::assertNull($options->rateLimiter());
        static::assertNull($options->idGenerator());
        static::assertNull($options->clock());
        static::assertNull($options->logger());
    }

    public function testEachWitherSetsOnlyItsOwnValue(): void
    {
        $limiter = new NoopRateLimiter();
        $ids = new RandomUuidGenerator(new SecureRandomBytes());
        $clock = new FrozenClock(new DateTimeImmutable('2026-11-15T10:00:00Z'));
        $logger = new NullLogger();
        $defaults = GatewayOptions::defaults();

        $withLimiter = $defaults->withRateLimiter($limiter);
        static::assertSame($limiter, $withLimiter->rateLimiter());
        static::assertNull($withLimiter->idGenerator());
        static::assertNull($withLimiter->clock());
        static::assertNull($withLimiter->logger());

        $withIds = $defaults->withIdGenerator($ids);
        static::assertNull($withIds->rateLimiter());
        static::assertSame($ids, $withIds->idGenerator());
        static::assertNull($withIds->clock());
        static::assertNull($withIds->logger());

        $withClock = $defaults->withClock($clock);
        static::assertNull($withClock->rateLimiter());
        static::assertNull($withClock->idGenerator());
        static::assertSame($clock, $withClock->clock());
        static::assertNull($withClock->logger());

        $withLogger = $defaults->withLogger($logger);
        static::assertNull($withLogger->rateLimiter());
        static::assertNull($withLogger->idGenerator());
        static::assertNull($withLogger->clock());
        static::assertSame($logger, $withLogger->logger());
    }

    public function testWithersChainAndKeepTheOthers(): void
    {
        $limiter = new NoopRateLimiter();
        $ids = new RandomUuidGenerator(new SecureRandomBytes());
        $clock = new FrozenClock(new DateTimeImmutable('2026-11-15T10:00:00Z'));
        $logger = new NullLogger();

        $options = GatewayOptions::defaults()
            ->withRateLimiter($limiter)
            ->withIdGenerator($ids)
            ->withClock($clock)
            ->withLogger($logger);

        static::assertSame($limiter, $options->rateLimiter());
        static::assertSame($ids, $options->idGenerator());
        static::assertSame($clock, $options->clock());
        static::assertSame($logger, $options->logger());
    }

    public function testOptionsAreImmutable(): void
    {
        $defaults = GatewayOptions::defaults();

        $defaults->withLogger(new NullLogger());

        static::assertNull($defaults->logger());
    }
}
