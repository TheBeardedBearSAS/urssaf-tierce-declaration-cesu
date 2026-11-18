<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Explicit JSON shape of a {@see DeclarationResult}: the declaration id and the amounts in cents (personal data of the
 * employee, ADR 0007).
 *
 * @phpstan-type ResultData array{declarationId: string, contributions: array{employer: int, employee: int}|null, withholdingTax: int|null, employerContributionsDebit: int|null, employerWithholdingTaxDebit: int|null, employerSalaryDebit: int|null, grossSalary: int|null, netTaxableSalary: int|null, salaryTransfer: int|null, aiciAmount: int|null}
 */
final class DeclarationResultSerializer
{
    /**
     * @return ResultData
     */
    public static function toArray(DeclarationResult $result): array
    {
        $contributions = $result->contributions();

        return [
            'declarationId' => $result->declarationId()->value(),
            'contributions' => $contributions instanceof Contributions ? [
                'employer' => $contributions->employerContributions()->cents(),
                'employee' => $contributions->employeeContributions()->cents(),
            ] : null,
            'withholdingTax' => self::cents($result->withholdingTax()),
            'employerContributionsDebit' => self::cents($result->employerContributionsDebit()),
            'employerWithholdingTaxDebit' => self::cents($result->employerWithholdingTaxDebit()),
            'employerSalaryDebit' => self::cents($result->employerSalaryDebit()),
            'grossSalary' => self::cents($result->grossSalary()),
            'netTaxableSalary' => self::cents($result->netTaxableSalary()),
            'salaryTransfer' => self::cents($result->salaryTransfer()),
            'aiciAmount' => self::cents($result->aiciAmount()),
        ];
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape ResultData
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): DeclarationResult
    {
        /** @var ResultData $data */
        $contributions = $data['contributions'];

        return new DeclarationResult(
            DeclarationId::fromString($data['declarationId']),
            $contributions === null ? null : Contributions::of(
                Money::fromCents($contributions['employer']),
                Money::fromCents($contributions['employee']),
            ),
            self::money($data['withholdingTax']),
            self::money($data['employerContributionsDebit']),
            self::money($data['employerWithholdingTaxDebit']),
            self::money($data['employerSalaryDebit']),
            self::money($data['grossSalary']),
            self::money($data['netTaxableSalary']),
            self::money($data['salaryTransfer']),
            self::money($data['aiciAmount']),
        );
    }

    private static function cents(?Money $amount): ?int
    {
        return $amount?->cents();
    }

    /**
     * @throws InvalidValue
     */
    private static function money(?int $cents): ?Money
    {
        return $cents === null ? null : Money::fromCents($cents);
    }
}
