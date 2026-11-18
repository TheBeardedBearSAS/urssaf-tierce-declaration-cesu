<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

use Countable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;

/**
 * Immutable ordered list of {@see ApiError}.
 */
final readonly class ApiErrors implements Countable
{
    /**
     * @param list<ApiError> $errors
     */
    private function __construct(private array $errors) {}

    public static function none(): self
    {
        return new self([]);
    }

    public static function of(ApiError ...$errors): self
    {
        return new self(array_values($errors));
    }

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    public function hasCode(string $code): bool
    {
        foreach ($this->errors as $error) {
            if ($error->hasCode($code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn(ApiError $error): string => $error->code(), $this->errors);
    }

    /**
     * @return list<ApiError>
     */
    public function all(): array
    {
        return $this->errors;
    }

    public function first(): ?ApiError
    {
        return $this->errors[0] ?? null;
    }

    /**
     * The errors as recorded on a declaration record (rejection, outcome unknown, failure), in order.
     *
     * @return list<SubmissionError>
     */
    public function toSubmissionErrors(): array
    {
        return array_map(static fn(ApiError $error): SubmissionError => $error->toSubmissionError(), $this->errors);
    }

    public function count(): int
    {
        return \count($this->errors);
    }
}
