<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

use LogicException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;

/**
 * Container factory of the simulated gateway: the runtime guard of the "gateway.simulated" rule for an environment that
 * is a placeholder at build time (the build-time rule only sees a literal "production").
 *
 * Source: lot 6 design §4.3 and open point 10, ADR 0013.
 */
final class SimulatedGatewayFactory
{
    /**
     * @param string $environment the resolved "environment" option
     *
     * @throws LogicException when the environment is "production"
     */
    public static function create(string $environment): SimulatedCesuGateway
    {
        if (Environment::from($environment) === Environment::Production) {
            throw new LogicException('"gateway.simulated" is meant for tests and cannot be used with the production environment.');
        }

        return new SimulatedCesuGateway();
    }
}
