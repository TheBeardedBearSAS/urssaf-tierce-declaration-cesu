<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;

/**
 * Method 010 (register a private employer): builds the `InputInscriptionPe` body (api-reference §2.1). The path is
 * {@see ApiPaths::registerEmployer()}. Internal identifiers (tenant, local ids, external reference) are never sent.
 */
final readonly class EmployerRegistrationRequestMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toBody(#[SensitiveParameter] NewEmployer $employer): array
    {
        $bankDetails = $employer->bankDetails();

        return [
            'codeCivilite' => $employer->civility()->value,
            'adresseEmploi' => AddressEncoding::encode($employer->address()),
            'nom' => $employer->lastName()->value(),
            'prenom' => $employer->firstName()->value(),
            'dateNaissance' => DateEncoding::encode($employer->birthDate()),
            'email' => $employer->email()->value(),
            'telephone' => $employer->phone()->value(),
            'rib' => [
                'iban' => $bankDetails->iban()->value(),
                'bic' => $bankDetails->bic()->value(),
                'mandatSepa' => $bankDetails->sepaMandate(),
            ],
        ];
    }
}
