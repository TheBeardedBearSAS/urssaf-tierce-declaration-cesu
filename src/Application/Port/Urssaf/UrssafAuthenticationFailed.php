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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;

/**
 * Token request refused, or HTTP 401 / 403 / 512 after a token refresh. Credentials or habilitation must be fixed;
 * not retryable. The message never contains credentials nor tokens.
 */
final class UrssafAuthenticationFailed extends RuntimeException implements UrssafException
{
    private function __construct(string $message, private readonly int $httpStatus)
    {
        parent::__construct($message, $httpStatus);
    }

    public static function withStatus(int $httpStatus): self
    {
        return new self(\sprintf('URSSAF authentication or authorisation failed (HTTP %d).', $httpStatus), $httpStatus);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
