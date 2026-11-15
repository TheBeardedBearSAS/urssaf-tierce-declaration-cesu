<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorClassifier;

final class ErrorClassifierTest extends TestCase
{
    private const UNKNOWN_TEXT = 'Un texte que URSSAF n\'a jamais documenté.';

    /**
     * @param list<string> $methods
     */
    #[DataProviderExternal(BusinessErrorCatalogueTest::class, 'catalogue')]
    public function testEveryVerbatimTextIsClassifiedOnItsOperationsOnly(BusinessError $error, string $text, array $methods): void
    {
        foreach (Operation::cases() as $operation) {
            $expected = \in_array($operation->methodNumber(), $methods, true) ? $error : null;

            static::assertSame($expected, ErrorClassifier::classifyDescription($operation, $text), $error->value . ' on ' . $operation->methodNumber());
        }
    }

    /**
     * @return iterable<string, array{Operation, string, BusinessError}>
     */
    public static function filledPlaceholders(): iterable
    {
        yield 'hourly wage, space before the euro sign' => [Operation::EstimateContributions, ' Le salaire horaire ne peut être inférieur à 11,65 €.', BusinessError::HourlyWageBelowMinimum];
        yield 'hourly wage, no space before the euro sign' => [Operation::EstimateContributions, ' Le salaire horaire ne peut être inférieur à 11,65€.', BusinessError::HourlyWageBelowMinimum];
        yield 'hourly wage, narrow no-break space' => [Operation::EstimateContributions, "Le salaire horaire ne peut être inférieur à 11,65\u{202F}€.", BusinessError::HourlyWageBelowMinimum];
        yield 'net hourly wage' => [Operation::DeclareSalary, 'Le salaire horaire net ne peut être inférieur à 11,65€.', BusinessError::NetHourlyWageBelowMinimum];
        yield 'specific hours wage' => [Operation::DeclareSalary, ' Le salaire horaire net pour une heure spécifique NUIT ne peut être inférieur à 12,30 €.', BusinessError::SpecificHoursWageBelowMinimum];
        yield 'specific hours wage, no space' => [Operation::EstimateContributions, ' Le salaire horaire net pour une heure spécifique NUIT ne peut être inférieur à 12,30€.', BusinessError::SpecificHoursWageBelowMinimum];
        yield 'full-time daily wage' => [Operation::DeclareSalary, ' Le salaire journalier à temps complet ne peut pas être inférieur à 2 SMIC.', BusinessError::FullTimeDailyWageBelowMinimum];
        yield 'part-time daily wage' => [Operation::EstimateContributions, ' Le salaire journalier à temps partiel ne peut pas être inférieur à 1,5 SMIC.', BusinessError::PartTimeDailyWageBelowMinimum];
        yield 'particular constraints range' => [Operation::DeclareSalary, ' Pour les sujétions particulières le nombre de smic net par jour ne peut être inférieur à 0,5 et supérieur à 3.', BusinessError::ParticularConstraintSmicOutOfRange];
        yield 'guaranteed minimum range' => [Operation::EstimateContributions, " Pour l'indemnité d'entretien le nombre de minimum garanti par jour ne peut être inférieur à 1 et supérieur à 4", BusinessError::GuaranteedMinimumOutOfRange];
    }

    #[DataProvider('filledPlaceholders')]
    public function testPlaceholdersAreFilledByUrssaf(Operation $operation, string $text, BusinessError $expected): void
    {
        static::assertSame($expected, ErrorClassifier::classifyDescription($operation, $text));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonMatchingTexts(): iterable
    {
        yield 'placeholder-only text (never classified)' => ['XXXXXX'];
        yield 'unknown text' => [self::UNKNOWN_TEXT];
        yield 'empty' => [''];
        yield 'placeholder without a value' => ['Le salaire horaire ne peut être inférieur à .'];
        yield 'prefix of a known text' => ['Veuillez indiquer un IBAN'];
        yield 'known text with a suffix' => ['Veuillez indiquer un IBAN valide. Merci.'];
        yield 'known text with a prefix' => ['Erreur : Veuillez indiquer un IBAN valide.'];
    }

    #[DataProvider('nonMatchingTexts')]
    public function testUnknownTextsAreNotClassified(string $text): void
    {
        foreach (Operation::cases() as $operation) {
            static::assertNull(ErrorClassifier::classifyDescription($operation, $text), $operation->methodNumber());
        }
    }

    /**
     * @return iterable<string, array{Operation, string, BusinessError}>
     */
    public static function normalisedVariants(): iterable
    {
        yield 'curly apostrophe for a straight one' => [Operation::DeclareSalary, 'La date de début d’emploi doit être antérieure ou égale à la date du jour.', BusinessError::EmploymentStartsAfterToday];
        yield 'straight apostrophe for a curly one' => [Operation::DeclareSalary, "Une déclaration est déjà enregistrée pour cette période d'emploi.", BusinessError::DuplicateDeclaration];
        yield 'curly apostrophe as documented' => [Operation::DeclareSalary, 'Une déclaration est déjà enregistrée pour cette période d’emploi.', BusinessError::DuplicateDeclaration];
        yield 'doubled apostrophe as documented' => [Operation::VerifyEmployer, " La date de naissance ne correspond pas à celle de l''employeur identifié par le numéro CESU renseigné.", BusinessError::BirthDateMismatch];
        yield 'single apostrophe for a doubled one' => [Operation::VerifyEmployer, "La date de naissance ne correspond pas à celle de l'employeur identifié par le numéro CESU renseigné.", BusinessError::BirthDateMismatch];
        yield 'tripled apostrophe' => [Operation::VerifyEmployer, "La date de naissance ne correspond pas à celle de l'''employeur identifié par le numéro CESU renseigné.", BusinessError::BirthDateMismatch];
        yield 'doubled curly apostrophe' => [Operation::VerifyEmployer, 'La date de naissance ne correspond pas à celle de l’’employeur identifié par le numéro CESU renseigné.', BusinessError::BirthDateMismatch];
        yield 'double inner spaces' => [Operation::RegisterEmployer, 'Veuillez  indiquer   un IBAN valide.', BusinessError::IbanInvalid];
        yield 'line break' => [Operation::RegisterEmployer, "Veuillez indiquer\nun IBAN valide.", BusinessError::IbanInvalid];
        yield 'tab and carriage return' => [Operation::RegisterEmployer, "Veuillez\tindiquer\r\nun IBAN valide.", BusinessError::IbanInvalid];
        yield 'no-break space' => [Operation::RegisterEmployer, "Veuillez indiquer un IBAN\u{00A0}valide.", BusinessError::IbanInvalid];
        yield 'narrow no-break space' => [Operation::RegisterEmployer, "Veuillez indiquer un IBAN\u{202F}valide.", BusinessError::IbanInvalid];
        yield 'leading and trailing spaces' => [Operation::RegisterEmployer, '   Veuillez indiquer un IBAN valide.  ', BusinessError::IbanInvalid];
        yield 'leading no-break space only' => [Operation::RegisterEmployer, "\u{00A0}Veuillez indiquer un IBAN valide.", BusinessError::IbanInvalid];
        yield 'trailing line break only' => [Operation::RegisterEmployer, "Veuillez indiquer un IBAN valide.\n", BusinessError::IbanInvalid];
        yield 'missing leading space of the documented text' => [Operation::EstimateContributions, 'Le salaire horaire ne peut être inférieur à 11,65 €.', BusinessError::HourlyWageBelowMinimum];
        yield 'upper case with accents' => [Operation::DeclareSalary, "UNE DÉCLARATION EST DÉJÀ ENREGISTRÉE POUR CETTE PÉRIODE D'EMPLOI.", BusinessError::DuplicateDeclaration];
        yield 'lower case' => [Operation::RegisterEmployer, 'veuillez indiquer un iban valide.', BusinessError::IbanInvalid];
    }

    #[DataProvider('normalisedVariants')]
    public function testClassificationSurvivesTheNormalisation(Operation $operation, string $text, BusinessError $expected): void
    {
        static::assertSame($expected, ErrorClassifier::classifyDescription($operation, $text));
    }
}
