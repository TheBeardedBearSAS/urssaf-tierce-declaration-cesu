<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

/**
 * Basis of the foster carer approval (`AgrementAF`, api-reference §2.7): exactly one of the two wire indicators is
 * true, which this enum encodes. Local value, no wire code.
 */
enum ApprovalBasis: string
{
    /** `indCertificationValiditeAgrement = true`, `indRemplacementPonctuel = false`. */
    case CertifiedApproval = 'certified_approval';

    /** `indCertificationValiditeAgrement = false`, `indRemplacementPonctuel = true`. */
    case OccasionalReplacement = 'occasional_replacement';
}
