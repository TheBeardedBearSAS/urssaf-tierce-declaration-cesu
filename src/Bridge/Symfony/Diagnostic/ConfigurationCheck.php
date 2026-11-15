<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic;

use Closure;
use Override;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use ValueError;

/**
 * The options resolved at run time (environment, base URI, headers, safety margin) are valid: an invalid value is a
 * failed check instead of a stack trace. The configuration is built by a closure, inside the check.
 *
 * Source: lot 6 design §6.1 (new check), ADR 0013.
 */
final readonly class ConfigurationCheck implements InstallationCheck
{
    private const NAME = 'configuration';

    /**
     * @param Closure(): ApiConfiguration $configuration builds the configuration (it may throw for an invalid option)
     */
    public function __construct(private Closure $configuration) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        // @mago-expect analysis:avoid-catching-error Environment::from() throws a ValueError for an unknown environment: it is reported by the check, not a crash.
        try {
            $configuration = ($this->configuration)();
        } catch (ValueError|InvalidValue $failure) {
            return [new CheckResult(self::NAME, '', CheckStatus::Failure, $failure->getMessage())];
        }

        return [new CheckResult(self::NAME, '', CheckStatus::Ok, \sprintf('%s: %s', $configuration->environment()->value, $configuration->baseUri()))];
    }
}
