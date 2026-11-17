<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;

/**
 * Thrown when a validation result containing errors must stop the current use case.
 */
final class ValidationFailed extends DomainException
{
    private function __construct(private readonly ValidationResult $result)
    {
        $errorCount = \count($result->errors());
        parent::__construct(
            \sprintf('Validation failed with %d error(s).', $errorCount),
            'validation.failed',
            ['error_count' => $errorCount],
        );
    }

    public static function withResult(ValidationResult $result): self
    {
        return new self($result);
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }
}
