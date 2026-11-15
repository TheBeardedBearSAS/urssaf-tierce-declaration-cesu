<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

/**
 * Meaning of the error code of a 400 or 500 answer (api-reference §3.13, ADR 0005). The wire codes are mapped by the
 * infrastructure layer: the Application never compares wire strings.
 */
enum ErrorKind: string
{
    /** `PARAM_INVALIDE`: a parameter is missing or malformed. */
    case InvalidParameter = 'invalid_parameter';

    /** `ERR_METIER`: a business rule refused the call, see {@see BusinessError}. */
    case Business = 'business';

    /** `SEPA_ABSENT` (010): the employer has no SEPA mandate. */
    case SepaMissing = 'sepa_missing';

    /** `MANDAT_INEXISTANT` (050, 060): the platform holds no mandate for the employer. */
    case MandateMissing = 'mandate_missing';

    /** `RIB_ABSENT` (050): bank details are missing. */
    case RibMissing = 'rib_missing';

    /** `COMPTE_DEJA_MANDATE` (030, 031): another platform already holds the mandate. */
    case MandateHeldElsewhere = 'mandate_held_elsewhere';

    /** `ER_API_020_0000` (030, 031): the employer account is unknown. */
    case AccountUnknown = 'account_unknown';

    /** `AICI_NON_ACTIVE` (050): the AICI service is not active. */
    case AiciNotActive = 'aici_not_active';

    /** `NATURE_INCORRECTE` (050): the nature of activity is incompatible. */
    case NatureIncompatible = 'nature_incompatible';

    /** `NATURE_INCORRECTE_AF` (050): the nature of activity is forbidden for a family foster carer. */
    case NatureForbiddenForFosterCarer = 'nature_forbidden_for_foster_carer';

    /** `NATURE_INCONNUE` (050): the nature of activity is unknown. */
    case NatureUnknown = 'nature_unknown';

    /** `NATURE_ABSENTE` (050): the nature of activity is missing. */
    case NatureMissing = 'nature_missing';

    /** `ACOMPTE_ELEVE` (050): the deposit is too high. */
    case DepositTooHigh = 'deposit_too_high';

    /** `ERR_TECHNIQUE`, `ERREUR_TECHNIQUE` (500): technical error on the URSSAF side. */
    case Technical = 'technical';

    /** A code outside the documented ones, or no error at all. */
    case Unknown = 'unknown';
}
