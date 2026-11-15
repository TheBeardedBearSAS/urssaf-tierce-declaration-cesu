<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\BirthMunicipalityName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\CivilName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

/**
 * One method per invariant or accessor of the request.
 *
 * @mago-expect lint:too-many-methods
 */
final class AiciActivationRequestTest extends TestCase
{
    public function testTheFixtureValues(): void
    {
        $employer = EmployerSamples::employer();
        $birthName = CivilName::birthName('Dupond');
        $usageName = CivilName::usageName('Dupond');
        $firstName = CivilName::firstName('Marcel');
        $country = CountryCode::france();
        $code = MunicipalityCode::fromString('69381');
        $name = BirthMunicipalityName::fromString('LYON 01');

        $request = new AiciActivationRequest($employer, Civility::Male, $birthName, $usageName, $firstName, $country, $code, $name);

        static::assertSame($employer, $request->employer());
        static::assertSame(Civility::Male, $request->civility());
        static::assertSame($birthName, $request->birthName());
        static::assertSame($usageName, $request->usageName());
        static::assertSame($firstName, $request->firstName());
        static::assertSame($country, $request->birthCountry());
        static::assertSame($code, $request->birthMunicipalityCode());
        static::assertSame($name, $request->birthMunicipalityName());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidCountryLengths(): iterable
    {
        yield 'one character' => ['1'];
        yield 'two characters' => ['99'];
    }

    #[DataProvider('provideInvalidCountryLengths')]
    public function testTheBirthCountryHasExactlyThreeCharacters(string $country): void
    {
        $exception = DomainAssertions::assertViolationCode(
            AiciActivationRequest::BIRTH_COUNTRY_INVALID,
            static fn(): AiciActivationRequest => self::request(CountryCode::fromString($country), null, null),
        );

        static::assertSame('The birth country must be a 3-character INSEE country code.', $exception->getMessage());
        static::assertInstanceOf(InvalidValue::class, $exception);
    }

    public function testAMultibyteThreeCharacterCountryIsAccepted(): void
    {
        $request = self::request(CountryCode::fromString('é10'), null, null);

        static::assertSame('é10', $request->birthCountry()->value());
    }

    public function testTheCountryLengthIsCheckedBeforeFrance(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            AiciActivationRequest::BIRTH_COUNTRY_INVALID,
            static fn(): AiciActivationRequest => self::request(CountryCode::fromString('10'), null, null),
        );

        static::assertSame('aici_activation_request.birth_country_invalid', $exception->violationCode());
    }

    public function testFranceRequiresTheMunicipalityCode(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            AiciActivationRequest::BIRTH_MUNICIPALITY_CODE_REQUIRED,
            static fn(): AiciActivationRequest => self::request(CountryCode::france(), null, BirthMunicipalityName::fromString('LYON 01')),
        );

        static::assertSame('The birth municipality code is required for a birth in France.', $exception->getMessage());
    }

    public function testFranceRequiresTheMunicipalityName(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            AiciActivationRequest::BIRTH_MUNICIPALITY_NAME_REQUIRED,
            static fn(): AiciActivationRequest => self::request(CountryCode::france(), MunicipalityCode::fromString('69381'), null),
        );

        static::assertSame('The birth municipality name is required for a birth in France.', $exception->getMessage());
    }

    public function testTheMunicipalityCodeIsCheckedBeforeTheName(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            AiciActivationRequest::BIRTH_MUNICIPALITY_CODE_REQUIRED,
            static fn(): AiciActivationRequest => self::request(CountryCode::france(), null, null),
        );

        static::assertSame('aici_activation_request.birth_municipality_code_required', $exception->violationCode());
    }

    public function testABirthAbroadNeedsNoMunicipality(): void
    {
        $request = self::request(CountryCode::fromString('350'), null, null);

        static::assertNull($request->birthMunicipalityCode());
        static::assertNull($request->birthMunicipalityName());
    }

    public function testMunicipalityDataIsAcceptedForABirthAbroad(): void
    {
        $request = self::request(
            CountryCode::fromString('350'),
            MunicipalityCode::fromString('99999'),
            BirthMunicipalityName::fromString('Madrid'),
        );

        static::assertSame('99999', $request->birthMunicipalityCode()?->value());
        static::assertSame('Madrid', $request->birthMunicipalityName()?->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDifferingParts(): iterable
    {
        yield 'employer Cesu number' => ['employerCesuNumber'];
        yield 'employer birth date' => ['employerBirthDate'];
        yield 'civility' => ['civility'];
        yield 'birth name' => ['birthName'];
        yield 'usage name' => ['usageName'];
        yield 'first name' => ['firstName'];
        yield 'country' => ['country'];
        yield 'municipality code' => ['municipalityCode'];
        yield 'municipality name' => ['municipalityName'];
    }

    #[DataProvider('provideDifferingParts')]
    public function testEquality(string $differingPart): void
    {
        $request = self::request(CountryCode::france(), MunicipalityCode::fromString('69381'), BirthMunicipalityName::fromString('LYON 01'));
        $different = self::different($differingPart);

        static::assertTrue($request->equals(self::request(CountryCode::france(), MunicipalityCode::fromString('69381'), BirthMunicipalityName::fromString('LYON 01'))));
        static::assertFalse($request->equals($different));
        static::assertFalse($different->equals($request));
    }

    public function testEqualityWithAbsentMunicipalityData(): void
    {
        $abroad = self::request(CountryCode::fromString('350'), null, null);
        $abroadWithCode = self::request(CountryCode::fromString('350'), MunicipalityCode::fromString('99999'), null);
        $abroadWithName = self::request(CountryCode::fromString('350'), null, BirthMunicipalityName::fromString('Madrid'));

        static::assertTrue($abroad->equals(self::request(CountryCode::fromString('350'), null, null)));
        static::assertFalse($abroad->equals($abroadWithCode));
        static::assertFalse($abroadWithCode->equals($abroad));
        static::assertFalse($abroad->equals($abroadWithName));
        static::assertFalse($abroadWithName->equals($abroad));
    }

    public function testDebugOutputListsTheEightPartsAndMasksThem(): void
    {
        $request = self::request(CountryCode::france(), MunicipalityCode::fromString('69381'), BirthMunicipalityName::fromString('LYON 01'));

        $debug = $request->__debugInfo();

        static::assertSame($request->employer(), $debug['employer']);
        static::assertSame(Civility::Male, $debug['civility']);
        static::assertSame($request->birthName(), $debug['birthName']);
        static::assertSame($request->usageName(), $debug['usageName']);
        static::assertSame($request->firstName(), $debug['firstName']);
        static::assertSame($request->birthCountry(), $debug['birthCountry']);
        static::assertSame($request->birthMunicipalityCode(), $debug['birthMunicipalityCode']);
        static::assertSame($request->birthMunicipalityName(), $debug['birthMunicipalityName']);
        static::assertSame(
            ['employer', 'civility', 'birthName', 'usageName', 'firstName', 'birthCountry', 'birthMunicipalityCode', 'birthMunicipalityName'],
            array_keys($debug),
        );
        $dump = print_r($request, true);
        foreach (['Dupond', 'Marcel', '69381', 'LYON', '1975'] as $personalData) {
            static::assertStringNotContainsString($personalData, $dump);
        }
    }

    private static function request(CountryCode $country, ?MunicipalityCode $code, ?BirthMunicipalityName $name): AiciActivationRequest
    {
        return new AiciActivationRequest(
            EmployerSamples::employer(),
            Civility::Male,
            CivilName::birthName('Dupond'),
            CivilName::usageName('Dupond'),
            CivilName::firstName('Marcel'),
            $country,
            $code,
            $name,
        );
    }

    /**
     * The reference request with one part changed.
     *
     * @throws InvalidValue
     */
    private static function different(string $part): AiciActivationRequest
    {
        $employer = EmployerSamples::employer();
        $france = CountryCode::france();
        $code = MunicipalityCode::fromString('69381');
        $name = BirthMunicipalityName::fromString('LYON 01');

        return match ($part) {
            'employerCesuNumber' => new AiciActivationRequest(Employer::of(CesuNumber::fromString('Z0003752390010'), CalendarDate::fromString('1975-01-30')), Civility::Male, CivilName::birthName('Dupond'), CivilName::usageName('Dupond'), CivilName::firstName('Marcel'), $france, $code, $name),
            'employerBirthDate' => new AiciActivationRequest(Employer::of(CesuNumber::fromString('Z0003752390009'), CalendarDate::fromString('1975-01-31')), Civility::Male, CivilName::birthName('Dupond'), CivilName::usageName('Dupond'), CivilName::firstName('Marcel'), $france, $code, $name),
            'civility' => new AiciActivationRequest($employer, Civility::Female, CivilName::birthName('Dupond'), CivilName::usageName('Dupond'), CivilName::firstName('Marcel'), $france, $code, $name),
            'birthName' => new AiciActivationRequest($employer, Civility::Male, CivilName::birthName('Durand'), CivilName::usageName('Dupond'), CivilName::firstName('Marcel'), $france, $code, $name),
            'usageName' => new AiciActivationRequest($employer, Civility::Male, CivilName::birthName('Dupond'), CivilName::usageName('Durand'), CivilName::firstName('Marcel'), $france, $code, $name),
            'firstName' => new AiciActivationRequest($employer, Civility::Male, CivilName::birthName('Dupond'), CivilName::usageName('Dupond'), CivilName::firstName('Paul'), $france, $code, $name),
            'country' => new AiciActivationRequest($employer, Civility::Male, CivilName::birthName('Dupond'), CivilName::usageName('Dupond'), CivilName::firstName('Marcel'), CountryCode::fromString('351'), $code, $name),
            'municipalityCode' => new AiciActivationRequest($employer, Civility::Male, CivilName::birthName('Dupond'), CivilName::usageName('Dupond'), CivilName::firstName('Marcel'), $france, MunicipalityCode::fromString('69382'), $name),
            'municipalityName' => new AiciActivationRequest($employer, Civility::Male, CivilName::birthName('Dupond'), CivilName::usageName('Dupond'), CivilName::firstName('Marcel'), $france, $code, BirthMunicipalityName::fromString('LYON 02')),
            default => throw new LogicException('Unknown part.'),
        };
    }
}
