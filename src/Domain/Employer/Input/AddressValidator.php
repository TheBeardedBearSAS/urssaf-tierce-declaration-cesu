<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressRule;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Turns an {@see AddressDraft} into an {@see Address}, reporting every violation (notification pattern, ADR 0000).
 * Paths are relative to the address ("streetNumber", "postalCode"...).
 *
 * @implements Validator<AddressDraft>
 */
final readonly class AddressValidator implements Validator
{
    public function validate(object $subject): ValidationResult
    {
        $collector = new ViolationCollector();
        $this->parse($subject, $collector);

        return $collector->result();
    }

    /**
     * @return Address|null null when a violation was added
     */
    public function parse(AddressDraft $draft, ViolationCollector $collector): ?Address
    {
        $errorsBefore = $collector->errorCount();
        $rawNumber = $draft->streetNumber;
        $number = $rawNumber === null
            ? null
            : $collector->attempt('streetNumber', static fn(): StreetNumber => StreetNumber::fromInt($rawNumber));
        $letter = $collector->optional('streetLetter', $draft->streetLetter, StreetLetter::fromString(...));
        $type = $collector->optional('streetType', $draft->streetType, StreetType::fromString(...));
        $streetName = $collector->optional('streetName', $draft->streetName, AddressText::streetName(...));
        $complement = $collector->optional('complement', $draft->complement, AddressText::complement(...));
        $locality = $collector->optional('locality', $draft->locality, AddressText::locality(...));
        $municipalityCode = $collector->optional('municipalityCode', $draft->municipalityCode, MunicipalityCode::fromString(...));
        foreach (AddressRule::violatedBy(self::presence($draft)) as $rule) {
            $collector->add(Violation::error($rule->path(), $rule->code(), $rule->message()));
        }
        $partsAreValid = $collector->errorCount() === $errorsBefore;
        $postalCode = $collector->required('postalCode', 'postal_code', $draft->postalCode, PostalCode::fromString(...));
        if (!$partsAreValid || $postalCode === null) {
            return null;
        }

        return new Address($number, $letter, $type, $streetName, $complement, $locality, $municipalityCode, $postalCode);
    }

    /**
     * @return array{streetNumber: bool, streetLetter: bool, streetType: bool, streetName: bool, complement: bool, locality: bool}
     */
    private static function presence(AddressDraft $draft): array
    {
        return [
            'streetNumber' => $draft->streetNumber !== null,
            'streetLetter' => self::filled($draft->streetLetter),
            'streetType' => self::filled($draft->streetType),
            'streetName' => self::filled($draft->streetName),
            'complement' => self::filled($draft->complement),
            'locality' => self::filled($draft->locality),
        ];
    }

    private static function filled(?string $raw): bool
    {
        return $raw !== null && $raw !== '';
    }
}
