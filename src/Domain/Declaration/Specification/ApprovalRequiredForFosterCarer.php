<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * A foster carer declaration (`typeEmploi = AF`) requires the approval (`agrementAF`, api-reference §2.7).
 *
 * Violation: "declaration.approval_required" at "approval".
 *
 * @implements Validator<DeclarationContent>
 */
final readonly class ApprovalRequiredForFosterCarer implements Validator
{
    /**
     * @param DeclarationContent $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if (!$subject->employee()->employmentKind()->isFosterCarer() || $subject->approval() !== null) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'approval',
            'declaration.approval_required',
            'The approval is required for a foster carer.',
        ));
    }
}
