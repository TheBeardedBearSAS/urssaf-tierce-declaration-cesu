<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

use DateInterval;
use DateTimeZone;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use Throwable;

/**
 * Validation predicates of the configuration tree (each one is true when the value is invalid).
 *
 * One small predicate per rule of the tree, hence the method count and the total complexity.
 *
 * Source: reference ConfigurationRules, lot 6 design §3.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class ConfigurationRules
{
    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasBothCredentialSources(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string} $config */
        $config = $value;

        return $config['credentials'] !== [] && $config['credentials_provider'] !== null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasNoCredentialSource(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string} $config */
        $config = $value;

        return $config['credentials'] === [] && $config['credentials_provider'] === null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function simulatedGatewayInProduction(array $value): bool
    {
        /** @var array{environment: string, gateway: array{simulated: bool}} $config */
        $config = $value;

        return $config['gateway']['simulated'] && $config['environment'] === 'production';
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function lockEnabledWithoutComponent(array $value): bool
    {
        return self::lockRequiredButMissing($value, class_exists(LockFactory::class));
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function lockRequiredButMissing(array $value, bool $componentInstalled): bool
    {
        /** @var array{lock: array{enabled: bool|string}} $config */
        $config = $value;

        return $config['lock']['enabled'] === true && !$componentInstalled;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function schedulerEnabledWithoutComponent(array $value): bool
    {
        return self::schedulerRequiredButMissing($value, interface_exists(ScheduleProviderInterface::class));
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function schedulerRequiredButMissing(array $value, bool $componentInstalled): bool
    {
        /** @var array{scheduler: array{enabled: bool}} $config */
        $config = $value;

        return $config['scheduler']['enabled'] && !$componentInstalled;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function outboxWithoutStorage(array $value): bool
    {
        /** @var array{outbox: array{enabled: bool, service: ?string}, persistence: array{driver: string}} $config */
        $config = $value;

        return $config['outbox']['enabled'] && $config['outbox']['service'] === null
            && $config['persistence']['driver'] === BundleConfiguration::DRIVER_CUSTOM;
    }

    /**
     * Mirrors the "duplicate header" check of ApiConfiguration, caught at build time (header names are case
     * insensitive).
     *
     * @param array<array-key, mixed> $value
     */
    public static function duplicateHeaderNames(array $value): bool
    {
        /** @var array{request_id_header: ?string, application_id_header: array{name: ?string}} $config */
        $config = $value;
        $requestId = $config['request_id_header'];
        $applicationId = $config['application_id_header']['name'];

        return $requestId !== null && $applicationId !== null && strtolower($requestId) === strtolower($applicationId);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function incompleteApplicationIdHeader(array $value): bool
    {
        /** @var array{name: ?string, value: ?string} $header */
        $header = $value;

        return ($header['name'] === null || $header['name'] === '') !== ($header['value'] === null || $header['value'] === '');
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function defaultPageAboveMax(array $value): bool
    {
        /** @var array{default_page_size: int, max_page_size: int} $workflow */
        $workflow = $value;

        return $workflow['default_page_size'] > $workflow['max_page_size'];
    }

    /**
     * The "doctrine" driver needs DoctrineBundle (its entity manager services), checked by the bundle while the
     * container is prepared (the per-extension container used when loading cannot tell whether a bundle is registered).
     */
    public static function doctrineDriverWithoutBundle(string $driver, bool $bundleRegistered): bool
    {
        return $driver === BundleConfiguration::DRIVER_DOCTRINE && !$bundleRegistered;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function customDriverWithoutRepositories(array $value): bool
    {
        /** @var array{driver: string, employer_account_repository: ?string, mandate_repository: ?string, declaration_record_repository: ?string} $persistence */
        $persistence = $value;

        return $persistence['driver'] === BundleConfiguration::DRIVER_CUSTOM
            && ($persistence['employer_account_repository'] === null
                || $persistence['mandate_repository'] === null
                || $persistence['declaration_record_repository'] === null);
    }

    /**
     * The empty string is the dummy value of an environment variable placeholder while the container is validated:
     * an empty variable fails at runtime instead (Environment::from()).
     */
    public static function isUnknownEnvironment(mixed $environment): bool
    {
        return !\in_array($environment, ['', ...array_map(static fn(Environment $case): string => $case->value, Environment::cases())], true);
    }

    /**
     * Same as the environment: a placeholder is allowed, an unknown value fails at runtime (AgreementEncoding::from()).
     */
    public static function isUnknownAgreementEncoding(mixed $encoding): bool
    {
        return !\in_array($encoding, ['', ...array_map(static fn(AgreementEncoding $case): string => $case->value, AgreementEncoding::cases())], true);
    }

    public static function isUnknownTimeZone(mixed $zone): bool
    {
        return !\is_string($zone) || !\in_array($zone, DateTimeZone::listIdentifiers(), true);
    }

    public static function isInvalidOptionalInterval(mixed $value): bool
    {
        return $value !== null && self::isInvalidInterval($value);
    }

    public static function isInvalidInterval(mixed $value): bool
    {
        try {
            new DateInterval(\is_string($value) ? $value : '');
        } catch (Throwable) {
            return true;
        }

        return false;
    }
}
