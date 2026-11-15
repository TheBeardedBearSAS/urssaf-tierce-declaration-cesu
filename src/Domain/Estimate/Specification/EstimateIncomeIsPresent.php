<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * At least one income should be given (api-reference §2.6).
 *
 * Warning "estimate_request.income_missing" at the root: both incomes are optional in the contract, so the check does
 * not block.
 *
 * @implements Validator<EstimateRequest>
 */
final readonly class EstimateIncomeIsPresent implements Validator
{
    private const MESSAGE = 'No income is given: URSSAF requires hours or an income element.';

    /**
     * @param EstimateRequest $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if ($subject->hourlyIncome() !== null || $subject->fosterCarerIncome() !== null) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::warning('', 'estimate_request.income_missing', self::MESSAGE));
    }
}
