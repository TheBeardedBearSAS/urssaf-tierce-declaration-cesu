<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

/**
 * Records of one tenant by state. Every state is listed, in workflow order, with 0 when there is no record (ADR 0003).
 */
final readonly class TenantOverview
{
    /**
     * @param array<string, int> $employers    employer accounts by EmployerStage value
     * @param array<string, int> $mandates     mandates by MandateState value
     * @param array<string, int> $declarations declarations by DeclarationState value
     */
    public function __construct(
        public string $tenantId,
        public array $employers,
        public array $mandates,
        public array $declarations,
    ) {}

    /**
     * Declarations that wait for a manual action: outcome unknown, in error or with invalid credentials. Employers and
     * mandates have no failure state.
     */
    public function needingAttention(): int
    {
        return $this->declarations['outcome_unknown'] + $this->declarations['error'] + $this->declarations['invalid_credentials'];
    }

    /**
     * @return array{tenant_id: string, employers: array<string, int>, mandates: array<string, int>, declarations: array<string, int>, needing_attention: int}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'employers' => $this->employers,
            'mandates' => $this->mandates,
            'declarations' => $this->declarations,
            'needing_attention' => $this->needingAttention(),
        ];
    }
}
