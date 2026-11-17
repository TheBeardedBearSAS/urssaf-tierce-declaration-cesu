<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank;

/**
 * Bank details "Rib" of an employer (api-reference §2.1): IBAN, BIC and "mandatSepa". A missing SEPA mandate is not
 * refused locally (URSSAF answers SEPA_ABSENT). Never persisted (ADR 0007).
 */
final readonly class BankDetails
{
    private function __construct(
        private Iban $iban,
        private Bic $bic,
        private bool $sepaMandate,
    ) {}

    public static function of(Iban $iban, Bic $bic, bool $sepaMandate): self
    {
        return new self($iban, $bic, $sepaMandate);
    }

    public function iban(): Iban
    {
        return $this->iban;
    }

    public function bic(): Bic
    {
        return $this->bic;
    }

    public function sepaMandate(): bool
    {
        return $this->sepaMandate;
    }

    public function equals(self $other): bool
    {
        return $this->iban->equals($other->iban)
            && $this->bic->equals($other->bic)
            && $this->sepaMandate === $other->sepaMandate;
    }
}
