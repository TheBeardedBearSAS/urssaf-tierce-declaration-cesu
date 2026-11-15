<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\AllOf;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;

/**
 * The rule set of an estimate request (api-reference §2.6).
 */
final class EstimateRules
{
    /**
     * Rules that depend on the request only, enforced by {@see EstimateRequest::of()}.
     *
     * @return Validator<EstimateRequest>
     */
    public static function intrinsic(): Validator
    {
        return AllOf::of(new EstimateIncomeIsPresent(), new EstimateWorkedHours());
    }
}
