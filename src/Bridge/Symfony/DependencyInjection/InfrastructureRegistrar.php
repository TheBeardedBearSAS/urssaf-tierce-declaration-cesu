<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\Psr14EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\TokenStore\Psr6TokenStore;

/**
 * Generic infrastructure: clock, identifiers, events, credentials, access token cache, rate limiter.
 *
 * Source: reference InfrastructureRegistrar, lot 6 design §4.3.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class InfrastructureRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->services->set(Clock::class, Clock::class)
            ->factory(ClockFactory::class . '::create')
            ->args([new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $this->services->set(IdGenerator::class, RandomUuidGenerator::class)->args([new Definition(SecureRandomBytes::class)]);
        $this->services->set(EventPublisher::class, Psr14EventPublisher::class)->args([new Reference('event_dispatcher')]);
        $this->registerCredentials($config['credentials_provider'], $config['credentials']);
        $this->registerTokenStore($config['token_store']);
        $this->registerRateLimiter($config['rate_limiter']);
    }

    /**
     * @param array<string, array{client_id: string, client_secret: string}> $credentials
     */
    private function registerCredentials(?string $provider, array $credentials): void
    {
        if ($provider !== null) {
            $this->services->alias(CredentialsProvider::class, $provider);

            return;
        }
        $definitions = [];
        foreach ($credentials as $tenantId => $tenant) {
            $definitions[$tenantId] = (new Definition(Credentials::class, [$tenant['client_id'], $tenant['client_secret']]))
                ->setFactory([Credentials::class, 'of']);
        }
        $this->services->set(CredentialsProvider::class, InMemoryCredentialsProvider::class)->args([$definitions]);
    }

    /**
     * @param array{cache_pool: string, service: ?string} $tokenStore
     */
    private function registerTokenStore(array $tokenStore): void
    {
        if ($tokenStore['service'] !== null) {
            $this->services->alias(TokenStore::class, $tokenStore['service']);

            return;
        }
        $this->services->set(TokenStore::class, Psr6TokenStore::class)->args([new Reference($tokenStore['cache_pool'])]);
    }

    /**
     * @param array{enabled: bool, limit: int, window: int} $rateLimiter
     */
    private function registerRateLimiter(array $rateLimiter): void
    {
        if ($rateLimiter['enabled']) {
            $this->services->set(RateLimiter::class, SlidingWindowRateLimiter::class)
                ->args([new Reference(Clock::class), $rateLimiter['limit'], $rateLimiter['window']]);

            return;
        }
        $this->services->set(RateLimiter::class, NoopRateLimiter::class);
    }
}
