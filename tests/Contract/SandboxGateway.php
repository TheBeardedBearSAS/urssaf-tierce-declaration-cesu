<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Contract;

use DateTimeZone;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafEndpoints;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafHttpGateway;

/**
 * Wires the real HTTP gateway against the URSSAF sandbox from the URSSAF_SANDBOX_* variables. One instance is shared by
 * the whole run (one HTTP client, one token store, therefore one token and one recording of every exchange).
 */
final class SandboxGateway
{
    public const TENANT = 'contract-tests';

    private static self|false|null $shared = null;

    private function __construct(
        public readonly UrssafHttpGateway $gateway,
        public readonly UrssafHttpGateway $numericAgreementGateway,
        public readonly SandboxHttpClient $http,
        public readonly TenantId $tenantId,
        public readonly ApiConfiguration $configuration,
        public readonly ?string $tokenUriOverride,
        private readonly Credentials $credentials,
        private readonly Psr17Factory $factory,
    ) {}

    /**
     * Null without credentials (checked first: a misconfigured base URI without credentials skips, it does not fail).
     *
     * @throws SandboxMisconfigured
     */
    public static function fromEnvironment(SandboxEnvironment $env): ?self
    {
        if (!$env->hasCredentials()) {
            return null;
        }

        $credentials = $env->credentials();
        $tenantId = TenantId::fromString(self::TENANT);
        $baseUri = $env->baseUri();
        $configuration = new ApiConfiguration(Environment::Sandbox, $baseUri);
        $numericConfiguration = new ApiConfiguration(
            Environment::Sandbox,
            $baseUri,
            null,
            null,
            ApiConfiguration::DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS,
            ApiConfiguration::DEFAULT_USER_AGENT,
            new DateTimeZone(ApiConfiguration::DEFAULT_BUSINESS_TIME_ZONE),
            AgreementEncoding::Numeric,
        );

        $factory = new Psr17Factory();
        $http = new SandboxHttpClient(new Psr18Client(HttpClient::create(['timeout' => 30]), $factory, $factory), $env->tokenUri());
        $transport = new HttpTransport($http, $factory, $factory);
        $provider = new InMemoryCredentialsProvider([self::TENANT => $credentials]);
        $tokens = new InMemoryTokenStore();
        $options = GatewayOptions::defaults()->withRateLimiter(new NoopRateLimiter());

        SandboxReport::describeRun(
            (string) parse_url($configuration->baseUri(), \PHP_URL_HOST),
            (string) parse_url($env->tokenUri() ?? $configuration->tokenUri(), \PHP_URL_HOST),
            $env->writesAllowed(),
        );

        return new self(
            UrssafGatewayFactory::createWith($transport, $provider, $tokens, $configuration, $options),
            UrssafGatewayFactory::createWith($transport, $provider, $tokens, $numericConfiguration, $options),
            $http,
            $tenantId,
            $configuration,
            $env->tokenUri(),
            $credentials,
            $factory,
        );
    }

    /**
     * The process-wide instance, null without credentials.
     *
     * @throws SandboxMisconfigured
     */
    public static function shared(): ?self
    {
        self::$shared ??= self::fromEnvironment(SandboxEnvironment::fromProcess()) ?? false;

        return self::$shared instanceof self ? self::$shared : null;
    }

    /**
     * One token request, same form as the SDK sends, straight to the given URI (no rewrite). The token itself is never
     * kept: only the shape of the response is recorded.
     */
    public function rawTokenProbe(string $uri): Exchange
    {
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->credentials->clientId(),
            'client_secret' => $this->credentials->clientSecret(),
            'scope' => UrssafEndpoints::TOKEN_SCOPE,
        ]);
        $request = $this->factory->createRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->factory->createStream($body));

        return $this->http->probe($request);
    }
}
