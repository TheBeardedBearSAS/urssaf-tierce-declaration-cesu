<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafEndpoints;

/**
 * Paths of the nine operations (api-reference §2), relative to {@see UrssafEndpoints::API_BASE_PATH}.
 *
 * The employer Cesu number is not personal data, so a path may be logged; a query string may not (ADR 0007).
 */
final class ApiPaths
{
    /**
     * 010, `POST`.
     */
    public static function registerEmployer(): string
    {
        return '/employeurs';
    }

    /**
     * 020, `GET`.
     */
    public static function verifyEmployer(CesuNumber $employer): string
    {
        return self::employer($employer);
    }

    /**
     * 021, `GET`.
     */
    public static function verifyEmployee(): string
    {
        return '/salaries';
    }

    /**
     * 030 (`POST`) and 031 (`DELETE`).
     */
    public static function mandates(): string
    {
        return '/mandats';
    }

    /**
     * 040, `POST`.
     */
    public static function estimate(): string
    {
        return '/estimer';
    }

    /**
     * 050, `POST`.
     */
    public static function declare(CesuNumber $employer): string
    {
        return self::employer($employer) . '/declarer';
    }

    /**
     * 060, `POST`.
     */
    public static function activateCesuPlus(CesuNumber $employer): string
    {
        return self::employer($employer) . '/cesuplus';
    }

    /**
     * 070, `POST`.
     */
    public static function activateAici(CesuNumber $employer): string
    {
        return self::employer($employer) . '/aici';
    }

    private static function employer(CesuNumber $employer): string
    {
        return '/employeurs/' . rawurlencode($employer->value());
    }
}
