<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Salary supplement sub-category (`codeSousCategorie`, api-reference §3.8), each one belonging to one category.
 */
enum SupplementSubCategory: string
{
    case NightPresence = 'NUIT';
    case NightSickCare = 'GARD';
    case ResponsiblePresence = 'RESP';
    case Food = 'NOUR';
    case Housing = 'LOGE';
    case OtherBenefit = 'AUTR';
    case EmployeeContribution = 'PART';
    case Bonus = 'PRIM';
    case Mileage = 'KILO';
    case TransportCosts = 'FRAI';
    case DismissalIndemnity = 'LICE';
    case TerminationIndemnity = 'RETR';

    /**
     * @throws InvalidValue "supplement_sub_category.unknown"
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::unknown('supplement_sub_category');
    }

    public function category(): SupplementCategory
    {
        return match ($this) {
            self::NightPresence, self::NightSickCare, self::ResponsiblePresence => SupplementCategory::SpecificHours,
            self::Food, self::Housing, self::OtherBenefit => SupplementCategory::BenefitsInKind,
            self::EmployeeContribution => SupplementCategory::EmployeeContribution,
            self::Bonus => SupplementCategory::Bonus,
            self::Mileage, self::TransportCosts => SupplementCategory::Transport,
            self::DismissalIndemnity, self::TerminationIndemnity => SupplementCategory::EndOfContract,
        };
    }
}
