<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * What `urssaf:cesu:check` checks: the tenants, and whether URSSAF may be called.
 *
 * Source: reference Diagnostic\CheckContext, lot 6 design §6.1.
 */
final readonly class CheckContext
{
    /**
     * @param non-empty-list<TenantId> $tenants
     */
    private function __construct(public array $tenants, public bool $remoteCallsAllowed) {}

    /**
     * @param non-empty-list<TenantId> $tenants
     */
    public static function withRemoteCalls(array $tenants): self
    {
        return new self($tenants, true);
    }

    /**
     * @param non-empty-list<TenantId> $tenants
     */
    public static function withoutRemoteCalls(array $tenants): self
    {
        return new self($tenants, false);
    }
}
