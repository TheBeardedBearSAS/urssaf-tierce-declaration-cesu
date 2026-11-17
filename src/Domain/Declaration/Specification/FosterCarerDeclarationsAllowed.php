<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * Foster carer declarations are refused locally unless the workflow policy allows them (ADR 0009: the API refuses them
 * anyway, api-reference §1.2).
 *
 * Violation: "declaration.foster_carer_unsupported" at "employee.employmentKind".
 *
 * @implements Validator<DeclarationContent>
 */
final readonly class FosterCarerDeclarationsAllowed implements Validator
{
    public function __construct(private bool $allowed) {}

    /**
     * @param DeclarationContent $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if ($this->allowed || !$subject->employee()->employmentKind()->isFosterCarer()) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'employee.employmentKind',
            'declaration.foster_carer_unsupported',
            'Foster carer declarations are not supported by this workflow.',
        ));
    }
}
