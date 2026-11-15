<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\DependencyInjection;

/**
 * Identifiers of the services of the bundle that are not named after an interface.
 *
 * Source: reference ServiceIds, lot 6 design §4.2.
 */
final class ServiceIds
{
    public const SYMFONY_HTTP_CLIENT = 'urssaf_tierce_declaration_cesu.http_client';
    public const PSR18_CLIENT = 'urssaf_tierce_declaration_cesu.psr18_client';
    public const API_CONFIGURATION = 'urssaf_tierce_declaration_cesu.api_configuration';
    public const TOKEN_PROVIDER = 'urssaf_tierce_declaration_cesu.token_provider';

    /** Token provider of urssaf:cesu:check: its own in-memory store, so that every check asks URSSAF. */
    public const PROBE_TOKEN_PROVIDER = 'urssaf_tierce_declaration_cesu.probe_token_provider';
    public const JSON_CLIENT = 'urssaf_tierce_declaration_cesu.json_client';
}
