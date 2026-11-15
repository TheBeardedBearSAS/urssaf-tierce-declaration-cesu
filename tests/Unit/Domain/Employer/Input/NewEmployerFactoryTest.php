<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\BankDetailsDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class NewEmployerFactoryTest extends TestCase
{
    public function testOfficialExample(): void
    {
        $json = file_get_contents(__DIR__ . '/../../../../Fixtures/wire/register_employer_request.json');
        static::assertIsString($json);
        /** @var array{codeCivilite: string, nom: string, prenom: string, dateNaissance: string, email: string, telephone: string, adresseEmploi: array{numVoie: int, lettreVoie: string, typeVoie: string, libelleVoie: string, complementLocalisation: string, lieuDit: string, codeCommune: string, codePostal: string}, rib: array{iban: string, bic: string, mandatSepa: bool}} $wire */
        $wire = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $address = $wire['adresseEmploi'];
        $rib = $wire['rib'];
        $draft = new NewEmployerDraft(
            $wire['codeCivilite'],
            $wire['nom'],
            $wire['prenom'],
            $wire['dateNaissance'],
            $wire['email'],
            $wire['telephone'],
            new AddressDraft(
                $address['numVoie'],
                $address['lettreVoie'],
                $address['typeVoie'],
                $address['libelleVoie'],
                $address['complementLocalisation'],
                $address['lieuDit'],
                $address['codeCommune'],
                $address['codePostal'],
            ),
            new BankDetailsDraft($rib['iban'], $rib['bic'], $rib['mandatSepa']),
        );
        $factory = new NewEmployerFactory();

        static::assertSame([], DomainAssertions::summary($factory->validate($draft)));
        static::assertTrue($factory->fromDraft($draft)->equals(EmployerSamples::newEmployer()));
    }

    public function testFromDraft(): void
    {
        $employer = (new NewEmployerFactory())->fromDraft(EmployerSamples::newEmployerDraft());

        static::assertTrue($employer->equals(EmployerSamples::newEmployer()));
    }

    public function testEveryMissingFieldIsReported(): void
    {
        $result = (new NewEmployerFactory())->validate(new NewEmployerDraft(null, null, null, null, null, null, null, null));

        static::assertSame(
            [
                'error civility civility.required',
                'error lastName last_name.required',
                'error firstName first_name.required',
                'error birthDate birth_date.required',
                'error email email.required',
                'error phone phone.required',
                'error address address.location_required',
                'error address.postalCode postal_code.required',
                'error bankDetails.iban iban.required',
                'error bankDetails.bic bic.required',
                'error bankDetails.sepaMandate sepa_mandate.required',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testEveryInvalidFieldIsReported(): void
    {
        $draft = new NewEmployerDraft(
            '3',
            'Dup0nd',
            'Pierr3',
            '1975-02-30',
            'not an email',
            '123',
            new AddressDraft(0, null, null, 'Rue', null, null, null, '1'),
            new BankDetailsDraft('FR-76', 'BDFE!', false),
        );

        $result = (new NewEmployerFactory())->validate($draft);

        static::assertSame(
            [
                'error civility civility.unknown',
                'error lastName last_name.invalid_characters',
                'error firstName first_name.invalid_characters',
                'error birthDate calendar_date.invalid_date',
                'error email email_address.invalid_format',
                'error phone phone_number.invalid_format',
                'error address.streetNumber street_number.out_of_range',
                'error address.postalCode postal_code.invalid_format',
                'error bankDetails.iban iban.invalid_characters',
                'error bankDetails.bic bic.invalid_characters',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        $valid = EmployerSamples::newEmployerDraft();
        $cases = [
            'civility' => new NewEmployerDraft('9', $valid->lastName, $valid->firstName, $valid->birthDate, $valid->email, $valid->phone, $valid->address, $valid->bankDetails),
            'lastName' => new NewEmployerDraft($valid->civility, '1', $valid->firstName, $valid->birthDate, $valid->email, $valid->phone, $valid->address, $valid->bankDetails),
            'firstName' => new NewEmployerDraft($valid->civility, $valid->lastName, '1', $valid->birthDate, $valid->email, $valid->phone, $valid->address, $valid->bankDetails),
            'birthDate' => new NewEmployerDraft($valid->civility, $valid->lastName, $valid->firstName, 'x', $valid->email, $valid->phone, $valid->address, $valid->bankDetails),
            'email' => new NewEmployerDraft($valid->civility, $valid->lastName, $valid->firstName, $valid->birthDate, 'x', $valid->phone, $valid->address, $valid->bankDetails),
            'phone' => new NewEmployerDraft($valid->civility, $valid->lastName, $valid->firstName, $valid->birthDate, $valid->email, 'x', $valid->address, $valid->bankDetails),
            'address' => new NewEmployerDraft($valid->civility, $valid->lastName, $valid->firstName, $valid->birthDate, $valid->email, $valid->phone, new AddressDraft(null, null, null, null, null, null, null, null), $valid->bankDetails),
            'bankDetails' => new NewEmployerDraft($valid->civility, $valid->lastName, $valid->firstName, $valid->birthDate, $valid->email, $valid->phone, $valid->address, new BankDetailsDraft('FR76', 'BDFE', null)),
            'iban' => new NewEmployerDraft($valid->civility, $valid->lastName, $valid->firstName, $valid->birthDate, $valid->email, $valid->phone, $valid->address, new BankDetailsDraft(null, 'BDFE', true)),
            'bic' => new NewEmployerDraft($valid->civility, $valid->lastName, $valid->firstName, $valid->birthDate, $valid->email, $valid->phone, $valid->address, new BankDetailsDraft('FR76', null, true)),
        ];
        $factory = new NewEmployerFactory();

        foreach ($cases as $name => $draft) {
            static::assertFalse($factory->validate($draft)->isValid(), $name);
            try {
                $factory->fromDraft($draft);
                static::fail($name);
            } catch (ValidationFailed $failed) {
                static::assertSame('validation.failed', $failed->violationCode(), $name);
                static::assertFalse($failed->result()->isValid(), $name);
            }
        }
    }

    public function testFromDraftReportsEveryViolation(): void
    {
        try {
            (new NewEmployerFactory())->fromDraft(new NewEmployerDraft(null, null, null, null, null, null, null, null));
            static::fail('Expected a ValidationFailed.');
        } catch (ValidationFailed $failed) {
            static::assertCount(11, $failed->result()->errors());
            static::assertSame(['error_count' => 11], $failed->params());
        }
    }

    public function testASepaMandateSetToFalseIsAccepted(): void
    {
        $valid = EmployerSamples::newEmployerDraft();
        $draft = new NewEmployerDraft($valid->civility, $valid->lastName, $valid->firstName, $valid->birthDate, $valid->email, $valid->phone, $valid->address, new BankDetailsDraft('FR76', 'BDFE', false));

        static::assertFalse((new NewEmployerFactory())->fromDraft($draft)->bankDetails()->sepaMandate());
    }
}
