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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;

/**
 * Read model of the amounts URSSAF returned for a declaration (method 050), in cents; null when URSSAF did not return the
 * amount.
 */
final readonly class DeclarationResultView
{
    private function __construct(
        public string $declarationId,
        public ?int $employerContributionsCents,
        public ?int $employeeContributionsCents,
        public ?int $withholdingTaxCents,
        public ?int $employerContributionsDebitCents,
        public ?int $employerWithholdingTaxDebitCents,
        public ?int $employerSalaryDebitCents,
        public ?int $grossSalaryCents,
        public ?int $netTaxableSalaryCents,
        public ?int $salaryTransferCents,
        public ?int $aiciAmountCents,
    ) {}

    public static function fromResult(DeclarationResult $result): self
    {
        $contributions = $result->contributions();

        return new self(
            $result->declarationId()->value(),
            $contributions?->employerContributions()->cents(),
            $contributions?->employeeContributions()->cents(),
            $result->withholdingTax()?->cents(),
            $result->employerContributionsDebit()?->cents(),
            $result->employerWithholdingTaxDebit()?->cents(),
            $result->employerSalaryDebit()?->cents(),
            $result->grossSalary()?->cents(),
            $result->netTaxableSalary()?->cents(),
            $result->salaryTransfer()?->cents(),
            $result->aiciAmount()?->cents(),
        );
    }
}
