<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf;

/**
 * How the `accord` query parameter of method 060 is written (ADR 0008): the Swagger documents a boolean, the PDF
 * `1` / `0`. The bundle option takes the values `boolean` and `numeric`.
 */
enum AgreementEncoding: string
{
    case Boolean = 'boolean';
    case Numeric = 'numeric';

    /**
     * The parameter is the agreement itself (the value written on the wire), not a switch between two behaviours.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    public function encode(bool $agreement): string
    {
        return match ($this) {
            self::Boolean => $agreement ? 'true' : 'false',
            self::Numeric => $agreement ? '1' : '0',
        };
    }
}
