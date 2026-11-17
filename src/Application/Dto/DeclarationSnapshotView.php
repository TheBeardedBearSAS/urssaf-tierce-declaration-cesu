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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationSnapshot;

/**
 * Read model of the non-personal summary kept with a declaration record (ADR 0007): no name, birth date, NIR or amount
 * of the payload.
 */
final readonly class DeclarationSnapshotView
{
    private function __construct(
        public string $employerCesuNumber,
        public int $type,
        public string $employmentStart,
        public string $employmentEnd,
        public string $paymentDate,
        public string $employmentKind,
        public string $employeeReferenceKind,
        public ?string $employeeCesuNumber,
        public string $fingerprint,
    ) {}

    public static function fromSnapshot(DeclarationSnapshot $snapshot): self
    {
        $period = $snapshot->employmentPeriod();

        return new self(
            $snapshot->employerCesuNumber()->value(),
            $snapshot->type()->value,
            $period->start()->toString(),
            $period->end()->toString(),
            $snapshot->paymentDate()->toString(),
            $snapshot->employmentKind()->value,
            $snapshot->employeeReferenceKind()->value,
            $snapshot->employeeCesuNumber()?->value(),
            $snapshot->fingerprint()->value(),
        );
    }
}
