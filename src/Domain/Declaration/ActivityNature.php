<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Activity nature (`natureActivite`, api-reference §3.12), required for Cesu+ declarations and forbidden for foster
 * carers. Which natures are compatible with Cesu+ is not published (api-reference §5.22): not modelled.
 */
enum ActivityNature: string
{
    case DailyHousework = '03';
    case ShoppingOrLaundryDelivery = '04';
    case AdministrativeAssistance = '05';
    case Tutoring = '06';
    case HomeWatching = '07';
    case Gardening = '08';
    case HandymanWork = '09';
    case ItAssistance = '10';
    case ElderlyOrDisabledAssistance = '11';
    case TemporarilyDependentAssistance = '12';
    case MealsForElderlyOrDisabled = '13';
    case DrivingElderlyOrDisabled = '14';
    case DrivingTemporarilyDependent = '15';
    case PetCareForElderlyOrDisabled = '16';
    case BeautyCareForElderlyOrDisabled = '17';
    case SignLanguageInterpreting = '18';
    case RemoteAssistance = '19';
    case Childcare = '20';
    case DisabledChildcare = '21';
    case OtherServices = '22';

    /**
     * @throws InvalidValue "activity_nature.unknown" (NATURE_INCONNUE)
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::unknown('activity_nature');
    }
}
