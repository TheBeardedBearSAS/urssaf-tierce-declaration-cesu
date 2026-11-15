<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Exemption code of an estimate (`codeExoneration`, api-reference §3.6).
 */
enum ExemptionCode: string
{
    case NoExemption = '02';
    case OverSeventy = '01';
    case Aeeh = '03';
    case Disability80Percent = '05';
    case ThirdPartyAssistance = '06';
    case DisabilityCompensation = '70';
    case ChildDisabilityCompensation = '71';
    case PersonalisedAutonomyAllowance = '80';

    /**
     * @throws InvalidValue "exemption_code.unknown"
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::unknown('exemption_code');
    }
}
