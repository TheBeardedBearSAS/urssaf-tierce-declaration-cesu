<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;

/**
 * Error code catalogue of the URSSAF API (api-reference §3.13, PDF v2.0.2). Values are the exact wire codes.
 *
 * Unknown codes are legitimate (URSSAF may add some): use {@see self::fromCode()}, which returns null for them.
 */
enum ApiErrorCode: string
{
    case InvalidParameter = 'PARAM_INVALIDE';
    case Business = 'ERR_METIER';
    case SepaMissing = 'SEPA_ABSENT';
    case MandateMissing = 'MANDAT_INEXISTANT';
    case RibMissing = 'RIB_ABSENT';
    case MandateHeldElsewhere = 'COMPTE_DEJA_MANDATE';
    case AccountUnknown = 'ER_API_020_0000';
    case AiciNotActive = 'AICI_NON_ACTIVE';
    case NatureIncompatible = 'NATURE_INCORRECTE';
    case NatureForbiddenForFosterCarer = 'NATURE_INCORRECTE_AF';
    case NatureUnknown = 'NATURE_INCONNUE';
    case NatureMissing = 'NATURE_ABSENTE';
    case DepositTooHigh = 'ACOMPTE_ELEVE';
    case TechnicalError = 'ERR_TECHNIQUE';
    case MandateTechnicalError = 'ERREUR_TECHNIQUE';

    /**
     * Normalises a raw wire code: surrounding or inner whitespace is dropped (the official documentation contains
     * codes with stray spaces).
     */
    public static function normalise(string $rawCode): string
    {
        return preg_replace('/\s+/', '', $rawCode) ?? $rawCode;
    }

    public static function fromCode(string $rawCode): ?self
    {
        return self::tryFrom(self::normalise($rawCode));
    }

    /**
     * @return list<Operation> operations that document this code, in {@see Operation::cases()} order
     */
    public function operations(): array
    {
        return match ($this) {
            self::InvalidParameter => Operation::cases(),
            self::Business => [
                Operation::RegisterEmployer,
                Operation::VerifyEmployer,
                Operation::EstimateContributions,
                Operation::DeclareSalary,
                Operation::ActivateCesuPlus,
                Operation::ActivateAici,
            ],
            self::SepaMissing => [Operation::RegisterEmployer],
            self::MandateMissing => [Operation::DeclareSalary, Operation::ActivateCesuPlus],
            self::RibMissing,
            self::AiciNotActive,
            self::NatureIncompatible,
            self::NatureForbiddenForFosterCarer,
            self::NatureUnknown,
            self::NatureMissing,
            self::DepositTooHigh => [Operation::DeclareSalary],
            self::MandateHeldElsewhere, self::AccountUnknown, self::MandateTechnicalError => [
                Operation::RegisterMandate,
                Operation::CancelMandate,
            ],
            self::TechnicalError => [
                Operation::RegisterEmployer,
                Operation::VerifyEmployer,
                Operation::VerifyEmployee,
                Operation::EstimateContributions,
                Operation::DeclareSalary,
                Operation::ActivateCesuPlus,
                Operation::ActivateAici,
            ],
        };
    }

    /**
     * The meaning the application sees (ADR 0005): the wire code itself never leaves the infrastructure layer.
     */
    public function errorKind(): ErrorKind
    {
        return match ($this) {
            self::InvalidParameter => ErrorKind::InvalidParameter,
            self::Business => ErrorKind::Business,
            self::SepaMissing => ErrorKind::SepaMissing,
            self::MandateMissing => ErrorKind::MandateMissing,
            self::RibMissing => ErrorKind::RibMissing,
            self::MandateHeldElsewhere => ErrorKind::MandateHeldElsewhere,
            self::AccountUnknown => ErrorKind::AccountUnknown,
            self::AiciNotActive => ErrorKind::AiciNotActive,
            self::NatureIncompatible => ErrorKind::NatureIncompatible,
            self::NatureForbiddenForFosterCarer => ErrorKind::NatureForbiddenForFosterCarer,
            self::NatureUnknown => ErrorKind::NatureUnknown,
            self::NatureMissing => ErrorKind::NatureMissing,
            self::DepositTooHigh => ErrorKind::DepositTooHigh,
            self::TechnicalError, self::MandateTechnicalError => ErrorKind::Technical,
        };
    }

    /**
     * Technical error (HTTP 500).
     */
    public function isTechnical(): bool
    {
        return $this === self::TechnicalError || $this === self::MandateTechnicalError;
    }
}
