<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Postal address "Adresse" of an employer (010 "adresseEmploi") or an employee (050 "infosSalarie.adresse"),
 * api-reference §2.1 and PDF §3.1.4. The public constructor is the factory (more than 5 parts).
 *
 * The postal code is required; the municipality code only disambiguates a postal code and is not checkable locally.
 * The "numVoie + lettreVoie + typeVoie + libelleVoie" line must not exceed 32 characters (PDF §3.1.4): with the
 * field maxima (4 + 1 + 1 + 3 + 1 + 21 = 31) the rule always holds, so it is not checked at runtime.
 */
final readonly class Address
{
    public const STREET_LINE_MAX_LENGTH = 32;

    /**
     * @throws InvalidValue The code and message of the first violated {@see AddressRule}
     */
    public function __construct(
        private ?StreetNumber $number,
        private ?StreetLetter $letter,
        private ?StreetType $type,
        private ?AddressText $streetName,
        private ?AddressText $complement,
        private ?AddressText $locality,
        private ?MunicipalityCode $municipalityCode,
        private PostalCode $postalCode,
    ) {
        $rules = AddressRule::violatedBy([
            'streetNumber' => $number instanceof StreetNumber,
            'streetLetter' => $letter instanceof StreetLetter,
            'streetType' => $type instanceof StreetType,
            'streetName' => $streetName instanceof AddressText,
            'complement' => $complement instanceof AddressText,
            'locality' => $locality instanceof AddressText,
        ]);
        if ($rules !== []) {
            throw InvalidValue::because($rules[0]->code(), $rules[0]->message());
        }
    }

    public function number(): ?StreetNumber
    {
        return $this->number;
    }

    public function letter(): ?StreetLetter
    {
        return $this->letter;
    }

    public function type(): ?StreetType
    {
        return $this->type;
    }

    public function streetName(): ?AddressText
    {
        return $this->streetName;
    }

    public function complement(): ?AddressText
    {
        return $this->complement;
    }

    public function locality(): ?AddressText
    {
        return $this->locality;
    }

    public function municipalityCode(): ?MunicipalityCode
    {
        return $this->municipalityCode;
    }

    public function postalCode(): PostalCode
    {
        return $this->postalCode;
    }

    public function equals(self $other): bool
    {
        return $this->number?->value() === $other->number?->value()
            && $this->letter?->value() === $other->letter?->value()
            && $this->type?->value() === $other->type?->value()
            && $this->streetName?->value() === $other->streetName?->value()
            && $this->complement?->value() === $other->complement?->value()
            && $this->locality?->value() === $other->locality?->value()
            && $this->municipalityCode?->value() === $other->municipalityCode?->value()
            && $this->postalCode->equals($other->postalCode);
    }
}
