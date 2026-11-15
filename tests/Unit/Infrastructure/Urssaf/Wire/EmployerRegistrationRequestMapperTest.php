<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetLetter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\StreetType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\BankDetails;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Bic;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Bank\Iban;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployerRegistrationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\BodyReader;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\RequestSamples;

final class EmployerRegistrationRequestMapperTest extends TestCase
{
    private static function employer(Civility $civility, Address $address, bool $sepaMandate): NewEmployer
    {
        return new NewEmployer(
            $civility,
            PersonName::lastName('Dupond'),
            PersonName::firstName('Pierre'),
            CalendarDate::fromString('1975-01-30'),
            EmailAddress::fromString('pierre.durand@example.com'),
            PhoneNumber::fromString('0412345678'),
            $address,
            BankDetails::of(Iban::fromString('FR7630001007941234567890185'), Bic::fromString('BDFEFRPPCCT'), $sepaMandate),
        );
    }

    private static function documentedAddress(): Address
    {
        return new Address(
            StreetNumber::fromInt(12),
            StreetLetter::fromString('B'),
            StreetType::fromString('R'),
            AddressText::streetName('De la Paix'),
            AddressText::complement('Batiment C'),
            null,
            MunicipalityCode::fromString('69383'),
            PostalCode::fromString('69003'),
        );
    }

    public function testOfficialExample(): void
    {
        $body = (new EmployerRegistrationRequestMapper())->toBody(
            self::employer(Civility::Male, self::documentedAddress(), true),
        );

        // The PDF example sends an empty "lieuDit": an absent locality is omitted instead (api-reference §2.1).
        $expected = Fixtures::decoded('register_employer_request');
        $address = BodyReader::object($expected, 'adresseEmploi');
        unset($address['lieuDit']);
        $expected['adresseEmploi'] = $address;

        static::assertEquals($expected, json_decode(JsonEncoder::encode($body), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testExactBodyOfAWomanWithoutSepaMandate(): void
    {
        $body = (new EmployerRegistrationRequestMapper())->toBody(
            self::employer(Civility::Female, RequestSamples::minimalAddress(), false),
        );

        static::assertSame(
            '{"codeCivilite":"2","adresseEmploi":{"lieuDit":"Le Hameau","codePostal":"69003"},"nom":"Dupond",'
            . '"prenom":"Pierre","dateNaissance":"1975-01-30","email":"pierre.durand@example.com",'
            . '"telephone":"0412345678","rib":{"iban":"FR7630001007941234567890185","bic":"BDFEFRPPCCT",'
            . '"mandatSepa":false}}',
            JsonEncoder::encode($body),
        );
    }

    public function testExactBodyOfTheDocumentedExample(): void
    {
        $body = (new EmployerRegistrationRequestMapper())->toBody(
            self::employer(Civility::Male, self::documentedAddress(), true),
        );

        static::assertSame(
            '{"codeCivilite":"1","adresseEmploi":{"numVoie":12,"lettreVoie":"B","typeVoie":"R",'
            . '"libelleVoie":"De la Paix","complementLocalisation":"Batiment C","codeCommune":"69383",'
            . '"codePostal":"69003"},"nom":"Dupond","prenom":"Pierre","dateNaissance":"1975-01-30",'
            . '"email":"pierre.durand@example.com","telephone":"0412345678","rib":{"iban":"FR7630001007941234567890185",'
            . '"bic":"BDFEFRPPCCT","mandatSepa":true}}',
            JsonEncoder::encode($body),
        );
    }

    public function testNoInternalIdentifierIsSent(): void
    {
        $body = (new EmployerRegistrationRequestMapper())->toBody(
            self::employer(Civility::Male, self::documentedAddress(), true),
        );

        static::assertSame(
            ['codeCivilite', 'adresseEmploi', 'nom', 'prenom', 'dateNaissance', 'email', 'telephone', 'rib'],
            array_keys($body),
        );
    }
}
