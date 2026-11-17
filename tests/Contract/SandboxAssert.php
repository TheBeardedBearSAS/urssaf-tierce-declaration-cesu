<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Contract;

use PHPUnit\Framework\Assert;
use ReflectionClass;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\ApiErrorCode;
use Throwable;

/**
 * Assertions and failure messages that never echo URSSAF texts (the API may repeat the submitted number).
 */
final class SandboxAssert
{
    public static function allCodesCatalogued(ApiErrors $errors): void
    {
        foreach ($errors->codes() as $code) {
            Assert::assertNotNull(ApiErrorCode::fromCode($code), \sprintf('Error code "%s" is not in the ApiErrorCode catalogue.', $code));
        }
    }

    /**
     * Class, status, kind and codes only: never getMessage() nor a description.
     */
    public static function describe(Throwable $throwable): string
    {
        $details = match (true) {
            $throwable instanceof UrssafRequestRejected => [
                'operation=' . $throwable->operation()->value,
                'status=' . $throwable->httpStatus(),
                'kind=' . $throwable->kind()->value,
                'codes=' . implode(',', $throwable->errors()->codes()),
            ],
            $throwable instanceof UrssafUnavailable, $throwable instanceof OutcomeUnknown => [
                'operation=' . $throwable->operation()->value,
                'status=' . ($throwable->httpStatus() ?? 'none'),
                'codes=' . implode(',', $throwable->errors()->codes()),
            ],
            $throwable instanceof UrssafAuthenticationFailed => ['status=' . $throwable->httpStatus(), self::authenticationHint()],
            default => [],
        };

        return implode(' ', [(new ReflectionClass($throwable))->getShortName(), ...$details]);
    }

    public static function authenticationHint(): string
    {
        return 'Token endpoint refused the sandbox credentials; see the token endpoint test and URSSAF_SANDBOX_TOKEN_URI.';
    }
}
