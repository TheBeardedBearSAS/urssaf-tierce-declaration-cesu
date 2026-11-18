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
 * At least one income (hourly or foster carer) should be given (api-reference §2.7 "Veuillez indiquer un nombre
 * d'heures" / "au moins un élément de rémunération").
 *
 * Warning "declaration.income_missing" at the root: both incomes are optional in the contract, so the check does not
 * block (the server answers ERR_METIER).
 *
 * @implements Validator<DeclarationContent>
 */
final readonly class IncomeIsPresent implements Validator
{
    private const MESSAGE = 'No income is given: URSSAF requires hours or an income element.';

    /**
     * @param DeclarationContent $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if ($subject->hourlyIncome() !== null || $subject->fosterCarerIncome() !== null) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::warning('', 'declaration.income_missing', self::MESSAGE));
    }
}
