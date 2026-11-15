<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\EmploymentPeriodDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\HourlyIncomeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * Reference ViolationListConverterTest, over the violations of the CESU factories (lot 6 design §8.1).
 * One test per conversion rule (errors, warnings, prefix, translation, parameters), hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class ViolationListConverterTest extends TestCase
{
    public function testEveryErrorBecomesAConstraintViolationWithTheCodeAndThePath(): void
    {
        $error = Violation::error('hourlyIncome.workedHours', 'worked_hours.out_of_range', 'Worked hours is outside the allowed range.');

        $list = (new ViolationListConverter(null))->convert(ValidationResult::of($error));

        static::assertCount(1, $list);
        $violation = self::first($list);
        static::assertSame('Worked hours is outside the allowed range.', $violation->getMessage());
        static::assertSame('worked_hours.out_of_range', $violation->getMessageTemplate());
        static::assertSame('worked_hours.out_of_range', $violation->getCode());
        static::assertSame('hourlyIncome.workedHours', $violation->getPropertyPath());
        static::assertSame([], $violation->getParameters());
        static::assertNull($violation->getRoot());
        static::assertNull($violation->getInvalidValue());
        static::assertNull($violation->getPlural());
        static::assertNull($violation->getConstraint());
        static::assertSame($error, $violation->getCause());
    }

    public function testTheViolationsOfADeclarationDraftHavePathsAndCodes(): void
    {
        $draft = new DeclarationDraft(
            DraftFixtures::valid()->employerCesuNumber,
            0,
            new EmploymentPeriodDraft('2026-12-01', '2026-12-31'),
            '2026-12-31',
            null,
            '10',
            new HourlyIncomeDraft('12.5', 100, 8, 9, 7),
            null,
            DraftFixtures::employee(),
            null,
        );

        $list = (new ViolationListConverter(null))->convert($this->failureOf($draft));

        static::assertSame(['employmentPeriod.startDate'], self::paths($list));
        static::assertSame('employment_period.starts_after_today', self::first($list)->getCode());
        static::assertSame('The employment period must not start after today.', self::first($list)->getMessage());
    }

    public function testTheViolationsOfAnEmployerDraftAreConverted(): void
    {
        $valid = EmployerSamples::newEmployerDraft();
        $draft = new NewEmployerDraft(
            $valid->civility,
            $valid->lastName,
            $valid->firstName,
            $valid->birthDate,
            $valid->email,
            $valid->phone,
            new AddressDraft(null, null, null, null, null, null, '69383', '69003'),
            $valid->bankDetails,
        );

        $result = (new NewEmployerFactory())->validate($draft);
        $list = (new ViolationListConverter(null))->convert($result);

        static::assertCount(1, $list);
        static::assertSame('address', self::first($list)->getPropertyPath());
        static::assertSame('address.location_required', self::first($list)->getCode());
    }

    public function testTheViolationOfAnAddressRuleKeepsItsPathAndCode(): void
    {
        $valid = EmployerSamples::newEmployerDraft();
        $draft = new NewEmployerDraft(
            $valid->civility,
            $valid->lastName,
            $valid->firstName,
            $valid->birthDate,
            $valid->email,
            $valid->phone,
            new AddressDraft(12, null, null, null, null, null, '69383', '69003'),
            $valid->bankDetails,
        );

        $list = (new ViolationListConverter(null))->convert((new NewEmployerFactory())->validate($draft));

        static::assertSame(['address.streetName'], self::paths($list));
        static::assertSame('street_name.required_with_street_number', self::first($list)->getCode());
    }

    public function testTheViolationsOfAValidationFailureAreConverted(): void
    {
        $result = ValidationResult::of(
            Violation::error('birthDate', 'calendar_date.invalid_format', 'The date has an invalid format.'),
            Violation::error('email', 'email_address.invalid_format', 'Email address has an invalid format.'),
        );

        $list = (new ViolationListConverter(null))->convert(ValidationFailed::withResult($result));

        static::assertSame(['birthDate', 'email'], self::paths($list));
    }

    public function testThePathPrefixIsPrependedToEveryPath(): void
    {
        $result = ValidationResult::of(
            Violation::error('address.postalCode', 'postal_code.required', 'The postal code is required.'),
            Violation::error('', 'declaration.income_missing', 'No income is given.'),
        );

        $list = (new ViolationListConverter(null))->convert($result, 'employer');

        static::assertSame(['employer.address.postalCode', 'employer'], self::paths($list));
    }

    public function testWarningsAreKeptOnlyOnRequest(): void
    {
        $result = ValidationResult::of(
            Violation::warning('', 'declaration.income_missing', 'No income is given.'),
            Violation::error('employmentPeriod.startDate', 'employment_period.starts_after_today', 'Starts after today.'),
        );
        $converter = new ViolationListConverter(null);

        static::assertSame(['employmentPeriod.startDate'], self::paths($converter->convert($result)));
        static::assertSame(['', 'employmentPeriod.startDate'], self::paths($converter->convertWithWarnings($result, '')));
    }

    public function testTheParametersBecomePlaceholdersAndAreGivenToTheTranslator(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'worked_hours.out_of_range' => 'Entre %min% et %max% (%ratio%, %allowed%, %blocked%, %unit%).',
        ], 'fr', 'urssaf_tierce_declaration_cesu');
        $translator->addResource('array', ['worked_hours.out_of_range' => 'Wrong domain.'], 'fr', 'messages');
        $error = Violation::error('hourlyIncome.workedHours', 'worked_hours.out_of_range', 'Worked hours must be between 0 and 300.', [
            'min' => 0,
            'max' => 300,
            'ratio' => 0.5,
            'allowed' => true,
            'blocked' => false,
            'unit' => 'HEURE',
        ]);

        $violation = self::first((new ViolationListConverter($translator))->convert(ValidationResult::of($error)));

        $parameters = ['%min%' => '0', '%max%' => '300', '%ratio%' => '0.5', '%allowed%' => 'true', '%blocked%' => 'false', '%unit%' => 'HEURE'];
        static::assertSame('Entre 0 et 300 (0.5, true, false, HEURE).', $violation->getMessage());
        static::assertSame($parameters, $violation->getParameters());
    }

    public function testTheShippedFrenchMessageIsUsedWhenATranslatorIsGiven(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__ . '/../../../../src/Bridge/Symfony/Resources/translations/urssaf_tierce_declaration_cesu.fr.yaml', 'fr', 'urssaf_tierce_declaration_cesu');
        $result = ValidationResult::of(Violation::error('employmentPeriod.startDate', 'employment_period.starts_after_today', 'Starts after today.'));

        $violation = self::first((new ViolationListConverter($translator))->convert($result));

        static::assertSame('La période d\'emploi ne doit pas commencer après aujourd\'hui.', $violation->getMessage());
    }

    private function failureOf(DeclarationDraft $draft): ValidationFailed
    {
        $result = (new DeclarationFactory())->validate($draft, DraftFixtures::context());
        static::assertFalse($result->isValid());

        return ValidationFailed::withResult($result);
    }

    private static function first(ConstraintViolationList $list): ConstraintViolation
    {
        $violation = $list->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);

        return $violation;
    }

    /**
     * @return list<string>
     */
    private static function paths(ConstraintViolationList $list): array
    {
        $paths = [];
        foreach ($list as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }
}
