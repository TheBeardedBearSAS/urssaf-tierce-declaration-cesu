<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection\ConfigurationRules;

/**
 * The predicates of the configuration tree (each one is true when the value is invalid), lot 6 design §3.
 *
 * One test per rule of the tree, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class ConfigurationRulesTest extends TestCase
{
    #[DataProvider('availability')]
    public function testALockIsOnlyMissingWhenItIsRequiredAndTheComponentIsNotInstalled(bool|string $enabled, bool $installed, bool $expected): void
    {
        static::assertSame($expected, ConfigurationRules::lockRequiredButMissing(['lock' => ['enabled' => $enabled]], $installed));
    }

    /**
     * @return iterable<string, array{bool|string, bool, bool}>
     */
    public static function availability(): iterable
    {
        yield 'required, missing' => [true, false, true];
        yield 'required, installed' => [true, true, false];
        yield 'auto, missing' => ['auto', false, false];
        yield 'disabled, missing' => [false, false, false];
    }

    public function testTheSchedulerIsOnlyMissingWhenItIsEnabledAndTheComponentIsNotInstalled(): void
    {
        static::assertTrue(ConfigurationRules::schedulerRequiredButMissing(['scheduler' => ['enabled' => true]], false));
        static::assertFalse(ConfigurationRules::schedulerRequiredButMissing(['scheduler' => ['enabled' => true]], true));
        static::assertFalse(ConfigurationRules::schedulerRequiredButMissing(['scheduler' => ['enabled' => false]], false));
    }

    public function testTheInstalledComponentsAreDetected(): void
    {
        static::assertFalse(ConfigurationRules::lockEnabledWithoutComponent(['lock' => ['enabled' => true]]));
        static::assertFalse(ConfigurationRules::schedulerEnabledWithoutComponent(['scheduler' => ['enabled' => true]]));
    }

    public function testAnEnvironmentPlaceholderIsAcceptedWhileTheContainerIsValidated(): void
    {
        static::assertFalse(ConfigurationRules::isUnknownEnvironment(''));
        static::assertFalse(ConfigurationRules::isUnknownEnvironment('sandbox'));
        static::assertFalse(ConfigurationRules::isUnknownEnvironment('production'));
        static::assertTrue(ConfigurationRules::isUnknownEnvironment('staging'));
        static::assertTrue(ConfigurationRules::isUnknownEnvironment(null));
    }

    public function testAnAgreementEncodingPlaceholderIsAcceptedWhileTheContainerIsValidated(): void
    {
        static::assertFalse(ConfigurationRules::isUnknownAgreementEncoding(''));
        static::assertFalse(ConfigurationRules::isUnknownAgreementEncoding('boolean'));
        static::assertFalse(ConfigurationRules::isUnknownAgreementEncoding('numeric'));
        static::assertTrue(ConfigurationRules::isUnknownAgreementEncoding('text'));
        static::assertTrue(ConfigurationRules::isUnknownAgreementEncoding(1));
    }

    public function testTimeZonesAndDurations(): void
    {
        static::assertFalse(ConfigurationRules::isUnknownTimeZone('Europe/Paris'));
        static::assertTrue(ConfigurationRules::isUnknownTimeZone('Mars/Phobos'));
        static::assertTrue(ConfigurationRules::isUnknownTimeZone(null));
        static::assertFalse(ConfigurationRules::isInvalidInterval('PT15M'));
        static::assertTrue(ConfigurationRules::isInvalidInterval('soon'));
        static::assertTrue(ConfigurationRules::isInvalidInterval(15));
        static::assertFalse(ConfigurationRules::isInvalidOptionalInterval(null));
        static::assertFalse(ConfigurationRules::isInvalidOptionalInterval('P1D'));
        static::assertTrue(ConfigurationRules::isInvalidOptionalInterval('often'));
    }

    #[DataProvider('headers')]
    public function testTheApplicationIdHeaderNeedsBothItsNameAndItsValueOrNeither(?string $name, ?string $value, bool $expected): void
    {
        static::assertSame($expected, ConfigurationRules::incompleteApplicationIdHeader(['name' => $name, 'value' => $value]));
    }

    /**
     * @return iterable<string, array{?string, ?string, bool}>
     */
    public static function headers(): iterable
    {
        yield 'neither' => [null, null, false];
        yield 'both' => ['X-Application', 'my-app', false];
        yield 'both empty' => ['', '', false];
        yield 'name only' => ['X-Application', null, true];
        yield 'value only' => [null, 'my-app', true];
        yield 'name and empty value' => ['X-Application', '', true];
        yield 'empty name and value' => ['', 'my-app', true];
    }

    #[DataProvider('headerNames')]
    public function testTheTwoOptionalHeadersMustBeDifferent(?string $requestId, ?string $applicationId, bool $expected): void
    {
        static::assertSame($expected, ConfigurationRules::duplicateHeaderNames([
            'request_id_header' => $requestId,
            'application_id_header' => ['name' => $applicationId, 'value' => 'v'],
        ]));
    }

    /**
     * @return iterable<string, array{?string, ?string, bool}>
     */
    public static function headerNames(): iterable
    {
        yield 'same' => ['X-Request-Id', 'X-Request-Id', true];
        yield 'same ignoring case' => ['X-Request-Id', 'x-request-id', true];
        yield 'different' => ['X-Request-Id', 'X-Application', false];
        yield 'no request id' => [null, 'X-Application', false];
        yield 'no application id' => ['X-Request-Id', null, false];
    }

    public function testThePageSizes(): void
    {
        static::assertTrue(ConfigurationRules::defaultPageAboveMax(['default_page_size' => 2, 'max_page_size' => 1]));
        static::assertFalse(ConfigurationRules::defaultPageAboveMax(['default_page_size' => 1, 'max_page_size' => 1]));
    }

    public function testTheDoctrineDriverIsUnavailable(): void
    {
        static::assertTrue(ConfigurationRules::doctrineDriverUnavailable(['driver' => 'doctrine']));
        static::assertFalse(ConfigurationRules::doctrineDriverUnavailable(['driver' => 'in_memory']));
        static::assertFalse(ConfigurationRules::doctrineDriverUnavailable(['driver' => 'custom']));
    }

    #[DataProvider('customDrivers')]
    public function testTheCustomDriverNeedsTheThreeRepositories(string $driver, ?string $accounts, ?string $mandates, ?string $declarations, bool $expected): void
    {
        static::assertSame($expected, ConfigurationRules::customDriverWithoutRepositories([
            'driver' => $driver,
            'employer_account_repository' => $accounts,
            'mandate_repository' => $mandates,
            'declaration_record_repository' => $declarations,
        ]));
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, ?string, bool}>
     */
    public static function customDrivers(): iterable
    {
        yield 'complete' => ['custom', 'a', 'm', 'd', false];
        yield 'no accounts' => ['custom', null, 'm', 'd', true];
        yield 'no mandates' => ['custom', 'a', null, 'd', true];
        yield 'no declarations' => ['custom', 'a', 'm', null, true];
        yield 'in memory ignores them' => ['in_memory', null, null, null, false];
    }

    #[DataProvider('outboxes')]
    public function testTheOutboxOfTheCustomDriverNeedsItsStorage(bool $enabled, ?string $service, string $driver, bool $expected): void
    {
        static::assertSame($expected, ConfigurationRules::outboxWithoutStorage([
            'outbox' => ['enabled' => $enabled, 'service' => $service],
            'persistence' => ['driver' => $driver],
        ]));
    }

    /**
     * @return iterable<string, array{bool, ?string, string, bool}>
     */
    public static function outboxes(): iterable
    {
        yield 'custom without service' => [true, null, 'custom', true];
        yield 'custom with service' => [true, 'my.outbox', 'custom', false];
        yield 'disabled' => [false, null, 'custom', false];
        yield 'in memory' => [true, null, 'in_memory', false];
    }

    public function testTheSimulatedGatewayIsRefusedInProduction(): void
    {
        static::assertTrue(ConfigurationRules::simulatedGatewayInProduction(['environment' => 'production', 'gateway' => ['simulated' => true]]));
        static::assertFalse(ConfigurationRules::simulatedGatewayInProduction(['environment' => 'sandbox', 'gateway' => ['simulated' => true]]));
        static::assertFalse(ConfigurationRules::simulatedGatewayInProduction(['environment' => 'production', 'gateway' => ['simulated' => false]]));
    }

    public function testTheCredentialSources(): void
    {
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        $credentials = ['tenant-1' => ['client_id' => 'a', 'client_secret' => 'b']];

        static::assertTrue(ConfigurationRules::hasBothCredentialSources(['credentials' => $credentials, 'credentials_provider' => 'p']));
        static::assertFalse(ConfigurationRules::hasBothCredentialSources(['credentials' => $credentials, 'credentials_provider' => null]));
        static::assertFalse(ConfigurationRules::hasBothCredentialSources(['credentials' => [], 'credentials_provider' => 'p']));
        static::assertTrue(ConfigurationRules::hasNoCredentialSource(['credentials' => [], 'credentials_provider' => null]));
        static::assertFalse(ConfigurationRules::hasNoCredentialSource(['credentials' => $credentials, 'credentials_provider' => null]));
        static::assertFalse(ConfigurationRules::hasNoCredentialSource(['credentials' => [], 'credentials_provider' => 'p']));
    }
}
