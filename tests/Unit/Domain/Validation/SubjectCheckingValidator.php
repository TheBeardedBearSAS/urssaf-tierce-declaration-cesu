<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Validation;

use LogicException;
use stdClass;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;

/**
 * Test double: returns a fixed result, provided it receives the expected subject.
 *
 * @implements Validator<stdClass>
 */
final readonly class SubjectCheckingValidator implements Validator
{
    public function __construct(
        private stdClass $expectedSubject,
        private ValidationResult $result,
    ) {}

    public function validate(object $subject): ValidationResult
    {
        if ($subject !== $this->expectedSubject) {
            throw new LogicException('Unexpected subject.');
        }

        return $this->result;
    }
}
