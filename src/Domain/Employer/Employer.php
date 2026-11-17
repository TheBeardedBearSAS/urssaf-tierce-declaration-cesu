<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Identification of an employer account in every call (api-reference §2.2, §2.3, §2.4, §2.5, §2.7, §2.8):
 * "numeroCesu" and "dateNaissance". The birth date is personal data (ADR 0007).
 */
final readonly class Employer
{
    private function __construct(
        private CesuNumber $cesuNumber,
        private CalendarDate $birthDate,
    ) {}

    public static function of(CesuNumber $cesuNumber, #[SensitiveParameter] CalendarDate $birthDate): self
    {
        return new self($cesuNumber, $birthDate);
    }

    public function cesuNumber(): CesuNumber
    {
        return $this->cesuNumber;
    }

    public function birthDate(): CalendarDate
    {
        return $this->birthDate;
    }

    public function equals(self $other): bool
    {
        return $this->cesuNumber->equals($other->cesuNumber) && $this->birthDate->equals($other->birthDate);
    }

    /**
     * Personal data: the birth date is masked in debug output (var_dump, print_r, dumpers).
     *
     * @return array{cesuNumber: CesuNumber, birthDate: string}
     */
    public function __debugInfo(): array
    {
        return ['cesuNumber' => $this->cesuNumber, 'birthDate' => '***'];
    }
}
