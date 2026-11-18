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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;

/**
 * Read model of a mandate (primitives only). Instants are ISO 8601 UTC strings.
 */
final readonly class MandateView
{
    private function __construct(
        public string $mandateId,
        public string $tenantId,
        public string $externalReference,
        public string $cesuNumber,
        public string $state,
        public string $notifiedAt,
        public ?string $confirmedAt,
        public ?string $cancelledAt,
        public ?bool $lastMandatePresent,
        public ?string $lastCheckedAt,
        public string $updatedAt,
    ) {}

    public static function fromMandate(Mandate $mandate): self
    {
        $check = $mandate->lastCheck();

        return new self(
            $mandate->id()->value(),
            $mandate->tenantId()->value(),
            $mandate->externalReference()->value(),
            $mandate->cesuNumber()->value(),
            $mandate->state()->value,
            $mandate->notifiedAt()->toIso8601(),
            $mandate->confirmedAt()?->toIso8601(),
            $mandate->cancelledAt()?->toIso8601(),
            $check?->mandatePresent(),
            $check?->checkedAt()->toIso8601(),
            $mandate->updatedAt()->toIso8601(),
        );
    }
}
