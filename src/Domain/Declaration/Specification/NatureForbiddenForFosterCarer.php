<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * A foster carer declaration (`typeEmploi = AF`) carries no activity nature (api-reference §2.7,
 * NATURE_INCORRECTE_AF). Together with the Cesu+ rule (a nature is required) it makes AF x Cesu+ always refused.
 *
 * Violation: "declaration.nature_forbidden_for_foster_carer" at "activityNature".
 *
 * @implements Validator<DeclarationContent>
 */
final readonly class NatureForbiddenForFosterCarer implements Validator
{
    /**
     * @param DeclarationContent $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if (!$subject->employee()->employmentKind()->isFosterCarer() || $subject->activityNature() === null) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'activityNature',
            'declaration.nature_forbidden_for_foster_carer',
            'The activity nature must not be given for a foster carer.',
        ));
    }
}
