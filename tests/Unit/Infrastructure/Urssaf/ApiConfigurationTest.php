<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpHeader;

final class ApiConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{Environment, string, string, string}>
     */
    public static function provideEnvironments(): iterable
    {
        yield 'sandbox' => [
            Environment::Sandbox,
            'https://api-edi.urssaf.fr',
            'https://api-edi.urssaf.fr/api/oauth/v1/token',
            'https://api-edi.urssaf.fr/tiersdecl/v1/cesu/employeurs',
        ];
        yield 'production' => [
            Environment::Production,
            'https://api.urssaf.fr',
            'https://api.urssaf.fr/api/oauth/v1/token',
            'https://api.urssaf.fr/tiersdecl/v1/cesu/employeurs',
        ];
    }

    #[DataProvider('provideEnvironments')]
    public function testItResolvesTheEnvironmentUris(
        Environment $environment,
        string $baseUri,
        string $tokenUri,
        string $apiUri,
    ): void {
        $configuration = ApiConfiguration::forEnvironment($environment);

        static::assertSame($environment, $configuration->environment());
        static::assertSame($baseUri, $configuration->baseUri());
        static::assertSame($tokenUri, $configuration->tokenUri());
        static::assertSame($apiUri, $configuration->apiUri('/employeurs'));
    }

    public function testItHasTheDocumentedDefaults(): void
    {
        $configuration = ApiConfiguration::forEnvironment(Environment::Sandbox);

        static::assertSame(60, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('thebeardedbear-urssaf-tierce-declaration-cesu', $configuration->userAgent());
        static::assertSame('Europe/Paris', $configuration->businessTimeZone()->getName());
        static::assertSame(AgreementEncoding::Boolean, $configuration->agreementEncoding());
        static::assertNull($configuration->requestIdHeaderName());
        static::assertNull($configuration->applicationIdHeader());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideBaseUriOverrides(): iterable
    {
        yield 'host only' => ['https://proxy.example.test', 'https://proxy.example.test'];
        yield 'trailing slash' => ['https://proxy.example.test/', 'https://proxy.example.test'];
        yield 'path with trailing slashes' => ['https://h/base//', 'https://h/base'];
        yield 'path' => ['https://h/base', 'https://h/base'];
        yield 'plain http with a port' => ['http://localhost:8080', 'http://localhost:8080'];
        yield 'upper case scheme' => ['HTTPS://h', 'HTTPS://h'];
    }

    #[DataProvider('provideBaseUriOverrides')]
    public function testItAppliesTheBaseUriOverride(string $override, string $expected): void
    {
        $configuration = new ApiConfiguration(Environment::Production, $override);

        static::assertSame($expected, $configuration->baseUri());
        static::assertSame($expected . '/api/oauth/v1/token', $configuration->tokenUri());
        static::assertSame($expected . '/tiersdecl/v1/cesu/mandats', $configuration->apiUri('/mandats'));
    }

    public function testItExposesCustomSettings(): void
    {
        $timeZone = new DateTimeZone('UTC');
        $application = HttpHeader::of('X-Application-Id', 'app-42');

        $configuration = new ApiConfiguration(
            Environment::Production,
            null,
            'X-Request-Id',
            $application,
            5,
            'my-agent/1.0 (contact)',
            $timeZone,
            AgreementEncoding::Numeric,
        );

        static::assertSame('X-Request-Id', $configuration->requestIdHeaderName());
        static::assertSame($application, $configuration->applicationIdHeader());
        static::assertSame(5, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('my-agent/1.0 (contact)', $configuration->userAgent());
        static::assertSame($timeZone, $configuration->businessTimeZone());
        static::assertSame(AgreementEncoding::Numeric, $configuration->agreementEncoding());
    }

    public function testItAcceptsAZeroSafetyMargin(): void
    {
        static::assertSame(
            0,
            (new ApiConfiguration(Environment::Sandbox, null, null, null, 0))->tokenSafetyMarginSeconds(),
        );
    }

    public function testItAcceptsTwoDifferentHeaderNamesInEitherRole(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, 'X-A', HttpHeader::of('X-B', 'v'));

        static::assertSame('X-A', $configuration->requestIdHeaderName());
        static::assertSame('X-B', $configuration->applicationIdHeader()?->name());
    }

    public function testItAcceptsAnApplicationIdHeaderWithoutRequestIdHeader(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, null, HttpHeader::of('X-B', 'v'));

        static::assertNull($configuration->requestIdHeaderName());
        static::assertSame('X-B', $configuration->applicationIdHeader()?->name());
    }
}
