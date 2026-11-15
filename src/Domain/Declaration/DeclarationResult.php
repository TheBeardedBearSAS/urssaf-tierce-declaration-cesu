<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;

/**
 * Result of a declaration (`OutputDeclaration`, api-reference §2.7). Amounts are in cents (§4.2) and nullable:
 * their presence is not published. `salaryTransfer` is Cesu+ only.
 *
 * The public constructor is the factory (more than 5 parts).
 */
final readonly class DeclarationResult
{
    public function __construct(
        private DeclarationId $declarationId,
        private ?Contributions $contributions,
        private ?Money $withholdingTax,
        private ?Money $employerContributionsDebit,
        private ?Money $employerWithholdingTaxDebit,
        private ?Money $employerSalaryDebit,
        private ?Money $grossSalary,
        private ?Money $netTaxableSalary,
        private ?Money $salaryTransfer,
        private ?Money $aiciAmount,
    ) {}

    /**
     * `idDeclaration`.
     */
    public function declarationId(): DeclarationId
    {
        return $this->declarationId;
    }

    /**
     * `montantsCotisations`.
     */
    public function contributions(): ?Contributions
    {
        return $this->contributions;
    }

    /**
     * `montantPAS`.
     */
    public function withholdingTax(): ?Money
    {
        return $this->withholdingTax;
    }

    /**
     * `montantPrelevementPECotisations`.
     */
    public function employerContributionsDebit(): ?Money
    {
        return $this->employerContributionsDebit;
    }

    /**
     * `montantPrelevementPEPAS`.
     */
    public function employerWithholdingTaxDebit(): ?Money
    {
        return $this->employerWithholdingTaxDebit;
    }

    /**
     * `montantPrelevementPESalaire`.
     */
    public function employerSalaryDebit(): ?Money
    {
        return $this->employerSalaryDebit;
    }

    /**
     * `montantSalaireBrut`.
     */
    public function grossSalary(): ?Money
    {
        return $this->grossSalary;
    }

    /**
     * `montantSalaireNetImposable`.
     */
    public function netTaxableSalary(): ?Money
    {
        return $this->netTaxableSalary;
    }

    /**
     * `montantVirementSalaire` (Cesu+ only).
     */
    public function salaryTransfer(): ?Money
    {
        return $this->salaryTransfer;
    }

    /**
     * `montantAICI`.
     */
    public function aiciAmount(): ?Money
    {
        return $this->aiciAmount;
    }
}
