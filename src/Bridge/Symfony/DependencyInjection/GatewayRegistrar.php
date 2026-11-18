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

use DateTimeZone;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ContributionEstimator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerRegistrar;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\MandateNotifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\SalaryDeclarer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpHeader;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;

/**
 * The URSSAF HTTP gateway and everything it needs: PSR-18 client, PSR-17 factories, access tokens, JSON client.
 *
 * It is wired piece by piece (not through UrssafGatewayFactory) to expose the probe token provider of
 * "urssaf:cesu:check"; the arguments are those of UrssafGatewayFactory::createWith().
 *
 * Source: reference GatewayRegistrar, lot 6 design §4.4.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class GatewayRegistrar
{
    private const LOGGER_TAG = 'monolog.logger';
    private const LOGGER_CHANNEL = 'urssaf_cesu';

    /** Ports implemented by the gateway, each one an alias of {@see CesuGateway}. */
    private const PORTS = [
        EmployerRegistrar::class,
        EmployerVerifier::class,
        EmployeeVerifier::class,
        MandateNotifier::class,
        ContributionEstimator::class,
        SalaryDeclarer::class,
        CesuPlusActivator::class,
        AiciActivator::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->services->set(ServiceIds::SYMFONY_HTTP_CLIENT, HttpClientInterface::class)
            ->factory(HttpClient::class . '::create')
            ->args([['timeout' => $config['http_timeout']]]);
        // Also provides the PSR-17 request and stream factories, whatever the PSR-18 client in use.
        $this->services->set(ServiceIds::PSR18_CLIENT, Psr18Client::class)->args([new Reference(ServiceIds::SYMFONY_HTTP_CLIENT)]);
        $this->services->set(ServiceIds::API_CONFIGURATION, ApiConfiguration::class)->args([
            // Resolved at runtime: the environment is usually an "%env(URSSAF_CESU_ENV)%" placeholder.
            (new Definition(Environment::class, [$config['environment']]))->setFactory([Environment::class, 'from']),
            $config['base_uri'],
            $config['request_id_header'],
            $this->applicationIdHeader($config['application_id_header']),
            $config['token_safety_margin'],
            ApiConfiguration::DEFAULT_USER_AGENT,
            new Definition(DateTimeZone::class, [$config['business_timezone']]),
            $this->agreementEncoding($config['agreement_encoding']),
        ]);

        $client = new Reference($config['http_client'] ?? ServiceIds::PSR18_CLIENT);
        $factories = new Reference(ServiceIds::PSR18_CLIENT);
        $logger = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        foreach ([ServiceIds::TOKEN_PROVIDER => new Reference(TokenStore::class), ServiceIds::PROBE_TOKEN_PROVIDER => new Definition(InMemoryTokenStore::class)] as $id => $tokenStore) {
            $this->services->set($id, OAuth2TokenProvider::class)
                ->args([
                    $client,
                    $factories,
                    $factories,
                    $tokenStore,
                    new Reference(Clock::class),
                    new Reference(ServiceIds::API_CONFIGURATION),
                    $logger,
                ])
                ->tag(self::LOGGER_TAG, ['channel' => self::LOGGER_CHANNEL]);
        }
        $this->services->set(ServiceIds::JSON_CLIENT, JsonApiClient::class)
            ->args([
                $client,
                $factories,
                $factories,
                new Reference(ServiceIds::TOKEN_PROVIDER),
                new Reference(RateLimiter::class),
                new Reference(IdGenerator::class),
                new Reference(Clock::class),
                new Reference(ServiceIds::API_CONFIGURATION),
                $logger,
            ])
            ->tag(self::LOGGER_TAG, ['channel' => self::LOGGER_CHANNEL]);

        $this->services->set(CesuGateway::class, UrssafHttpGateway::class)
            ->args([
                new Reference(CredentialsProvider::class),
                new Reference(ServiceIds::JSON_CLIENT),
                $this->agreementEncoding($config['agreement_encoding']),
            ])
            ->arg('$logger', $logger)
            ->tag(self::LOGGER_TAG, ['channel' => self::LOGGER_CHANNEL]);
        foreach (self::PORTS as $port) {
            $this->services->alias($port, CesuGateway::class);
        }
        if ($config['gateway']['simulated']) {
            $this->services->set(CesuGateway::class, SimulatedCesuGateway::class)
                ->factory(SimulatedGatewayFactory::class . '::create')
                ->args([$config['environment']]);
            $this->services->alias(SimulatedCesuGateway::class, CesuGateway::class)->public();
        }
    }

    /**
     * An empty name is "not configured", like null (ConfigurationRules::incompleteApplicationIdHeader()).
     *
     * @param array{name: ?string, value: ?string} $header
     */
    private function applicationIdHeader(array $header): ?Definition
    {
        if ($header['name'] === null || $header['name'] === '') {
            return null;
        }

        return (new Definition(HttpHeader::class, [$header['name'], $header['value']]))->setFactory([HttpHeader::class, 'of']);
    }

    private function agreementEncoding(string $encoding): Definition
    {
        return (new Definition(AgreementEncoding::class, [$encoding]))->setFactory([AgreementEncoding::class, 'from']);
    }
}
