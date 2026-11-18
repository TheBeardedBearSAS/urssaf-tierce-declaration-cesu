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

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use Throwable;

/**
 * A write that is not safe to retry ({@see Operation::isSafeToRetry()}: 010, 050, 060, 070) may have been processed by
 * URSSAF, but the answer is lost or unreadable (ADR 0003). It must not be resent automatically: an operator
 * reconciles it. Deliberately not a {@see \TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RetryableException}.
 */
final class OutcomeUnknown extends RuntimeException implements UrssafException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly ?int $httpStatus,
        private readonly ApiErrors $errors,
        Throwable $previous,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * @throws InvariantViolation "outcome_unknown.not_reached" when the failure cannot have reached URSSAF
     */
    public static function fromUnavailable(UrssafUnavailable $cause): self
    {
        if (!$cause->mayHaveReachedUrssaf()) {
            throw InvariantViolation::because(
                'outcome_unknown.not_reached',
                'Only a failure that may have reached URSSAF has an unknown outcome.',
            );
        }

        return new self(self::message($cause->operation()), $cause->operation(), $cause->httpStatus(), $cause->errors(), $cause);
    }

    /**
     * The write was answered 2xx but the answer could not be read: URSSAF processed it.
     */
    public static function afterUnreadableResponse(Operation $operation, int $httpStatus, UrssafException $cause): self
    {
        return new self(self::message($operation), $operation, $httpStatus, ApiErrors::none(), $cause);
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }

    private static function message(Operation $operation): string
    {
        return \sprintf(
            'The outcome of URSSAF method %s is unknown: the call may have been processed and must not be resent automatically.',
            $operation->methodNumber(),
        );
    }
}
