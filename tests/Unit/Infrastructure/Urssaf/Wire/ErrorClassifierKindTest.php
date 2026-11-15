<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorClassifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class ErrorClassifierKindTest extends TestCase
{
    private const UNKNOWN_TEXT = 'Un texte que URSSAF n\'a jamais documenté.';

    public function testOnlyBusinessErrorsAreClassified(): void
    {
        $text = 'Veuillez indiquer un IBAN valide.';

        static::assertNull(ErrorClassifier::businessError(Operation::RegisterEmployer, ApiErrors::of(ApiError::of('PARAM_INVALIDE', 'm', $text))));
        static::assertSame(
            BusinessError::IbanInvalid,
            ErrorClassifier::businessError(Operation::RegisterEmployer, ApiErrors::of(ApiError::of(' ERR_ METIER', 'm', $text))),
        );
    }

    public function testTheFirstClassifiableBusinessErrorWins(): void
    {
        $iban = ApiError::of('ERR_METIER', 'm', 'Veuillez indiquer un IBAN valide.');
        $bic = ApiError::of('ERR_METIER', 'm', 'Veuillez indiquer un BIC valide.');
        $unknown = ApiError::of('ERR_METIER', 'm', self::UNKNOWN_TEXT);
        $withoutDescription = ApiError::of('ERR_METIER', 'm', null);
        $notBusiness = ApiError::of('PARAM_INVALIDE', 'm', 'Veuillez indiquer un BIC valide.');

        static::assertSame(BusinessError::IbanInvalid, ErrorClassifier::businessError(Operation::RegisterEmployer, ApiErrors::of($iban, $bic)));
        static::assertSame(BusinessError::BicInvalid, ErrorClassifier::businessError(Operation::RegisterEmployer, ApiErrors::of($unknown, $bic)));
        static::assertSame(BusinessError::BicInvalid, ErrorClassifier::businessError(Operation::RegisterEmployer, ApiErrors::of($withoutDescription, $bic)));
        static::assertSame(BusinessError::BicInvalid, ErrorClassifier::businessError(Operation::RegisterEmployer, ApiErrors::of($notBusiness, $bic)));
        static::assertNull(ErrorClassifier::businessError(Operation::RegisterEmployer, ApiErrors::of($unknown, $withoutDescription, $notBusiness)));
        static::assertNull(ErrorClassifier::businessError(Operation::RegisterEmployer, ApiErrors::none()));
    }

    public function testErrMetierOnVerifyEmployeeNeverClassifies(): void
    {
        $error = ApiError::of('ERR_METIER', 'm', 'Veuillez indiquer un IBAN valide.');

        static::assertNull(ErrorClassifier::businessError(Operation::VerifyEmployee, ApiErrors::of($error)));
    }

    /**
     * @return iterable<string, array{string, ErrorKind}>
     */
    public static function errorKinds(): iterable
    {
        yield 'PARAM_INVALIDE' => ['PARAM_INVALIDE', ErrorKind::InvalidParameter];
        yield 'ERR_METIER' => ['ERR_METIER', ErrorKind::Business];
        yield 'SEPA_ABSENT' => ['SEPA_ABSENT', ErrorKind::SepaMissing];
        yield 'MANDAT_INEXISTANT' => ['MANDAT_INEXISTANT', ErrorKind::MandateMissing];
        yield 'RIB_ABSENT' => ['RIB_ABSENT', ErrorKind::RibMissing];
        yield 'COMPTE_DEJA_MANDATE' => ['COMPTE_DEJA_MANDATE', ErrorKind::MandateHeldElsewhere];
        yield 'ER_API_020_0000' => ['ER_API_020_0000', ErrorKind::AccountUnknown];
        yield 'AICI_NON_ACTIVE' => ['AICI_NON_ACTIVE', ErrorKind::AiciNotActive];
        yield 'NATURE_INCORRECTE' => ['NATURE_INCORRECTE', ErrorKind::NatureIncompatible];
        yield 'NATURE_INCORRECTE_AF' => ['NATURE_INCORRECTE_AF', ErrorKind::NatureForbiddenForFosterCarer];
        yield 'NATURE_INCONNUE' => ['NATURE_INCONNUE', ErrorKind::NatureUnknown];
        yield 'NATURE_ABSENTE' => ['NATURE_ABSENTE', ErrorKind::NatureMissing];
        yield 'ACOMPTE_ELEVE' => ['ACOMPTE_ELEVE', ErrorKind::DepositTooHigh];
        yield 'ERR_TECHNIQUE' => ['ERR_TECHNIQUE', ErrorKind::Technical];
        yield 'ERREUR_TECHNIQUE' => ['ERREUR_TECHNIQUE', ErrorKind::Technical];
        yield 'unknown code' => ['ERR_NEW_CODE', ErrorKind::Unknown];
        yield 'dirty code' => [' ERR_ METIER ', ErrorKind::Business];
    }

    #[DataProvider('errorKinds')]
    public function testErrorKind(string $code, ErrorKind $expected): void
    {
        static::assertSame($expected, ErrorClassifier::errorKind(ApiErrors::of(ApiError::of($code, 'm', null))));
    }

    public function testErrorKindOfNoErrorIsUnknownAndTheFirstErrorWins(): void
    {
        static::assertSame(ErrorKind::Unknown, ErrorClassifier::errorKind(ApiErrors::none()));
        static::assertSame(
            ErrorKind::MandateMissing,
            ErrorClassifier::errorKind(ApiErrors::of(ApiError::of('MANDAT_INEXISTANT', 'm', null), ApiError::of('PARAM_INVALIDE', 'm', null))),
        );
        static::assertSame(
            ErrorKind::Unknown,
            ErrorClassifier::errorKind(ApiErrors::of(ApiError::of('ERR_NEW_CODE', 'm', null), ApiError::of('PARAM_INVALIDE', 'm', null))),
        );
    }

    /**
     * @return iterable<string, array{string, Operation, ErrorKind, ?BusinessError}>
     */
    public static function fixtures(): iterable
    {
        yield 'error_param_invalide' => ['error_param_invalide', Operation::VerifyEmployer, ErrorKind::InvalidParameter, null];
        yield 'error_swagger_example' => ['error_swagger_example', Operation::VerifyEmployee, ErrorKind::InvalidParameter, null];
        yield 'error_sepa_absent' => ['error_sepa_absent', Operation::RegisterEmployer, ErrorKind::SepaMissing, null];
        yield 'error_err_metier_iban on 010' => ['error_err_metier_iban', Operation::RegisterEmployer, ErrorKind::Business, BusinessError::IbanInvalid];
        yield 'error_err_metier_iban on 050' => ['error_err_metier_iban', Operation::DeclareSalary, ErrorKind::Business, null];
        yield 'error_err_metier_cesuplus_unpaid_salary on 050' => ['error_err_metier_cesuplus_unpaid_salary', Operation::DeclareSalary, ErrorKind::Business, BusinessError::UnpaidSalary];
        yield 'error_err_metier_cesuplus_unpaid_salary on 010' => ['error_err_metier_cesuplus_unpaid_salary', Operation::RegisterEmployer, ErrorKind::Business, null];
        yield 'error_mandat_inexistant on 050' => ['error_mandat_inexistant', Operation::DeclareSalary, ErrorKind::MandateMissing, null];
        yield 'error_mandat_inexistant on 060' => ['error_mandat_inexistant', Operation::ActivateCesuPlus, ErrorKind::MandateMissing, null];
        yield 'error_compte_deja_mandate on 030' => ['error_compte_deja_mandate', Operation::RegisterMandate, ErrorKind::MandateHeldElsewhere, null];
        yield 'error_compte_deja_mandate on 031' => ['error_compte_deja_mandate', Operation::CancelMandate, ErrorKind::MandateHeldElsewhere, null];
        yield 'error_account_unknown' => ['error_account_unknown', Operation::RegisterMandate, ErrorKind::AccountUnknown, null];
        yield 'error_technical' => ['error_technical', Operation::DeclareSalary, ErrorKind::Technical, null];
        yield 'error_technical_mandate' => ['error_technical_mandate', Operation::CancelMandate, ErrorKind::Technical, null];
    }

    #[DataProvider('fixtures')]
    public function testRejectionOfTheOfficialErrorBodies(string $fixture, Operation $operation, ErrorKind $kind, ?BusinessError $businessError): void
    {
        $errors = (new ErrorPayloadParser())->parseBody(Fixtures::load($fixture));

        $rejection = ErrorClassifier::rejection($operation, 400, $errors);

        static::assertSame($operation, $rejection->operation());
        static::assertSame(400, $rejection->httpStatus());
        static::assertSame($errors, $rejection->errors());
        static::assertSame($kind, $rejection->kind());
        static::assertSame($businessError, $rejection->businessError());
    }

    public function testRejectionHelpers(): void
    {
        $iban = ErrorClassifier::rejection(
            Operation::RegisterEmployer,
            400,
            (new ErrorPayloadParser())->parseBody(Fixtures::load('error_err_metier_iban')),
        );
        $mandate = ErrorClassifier::rejection(
            Operation::DeclareSalary,
            400,
            (new ErrorPayloadParser())->parseBody(Fixtures::load('error_mandat_inexistant')),
        );

        static::assertTrue($iban->is(BusinessError::IbanInvalid));
        static::assertFalse($iban->isDuplicateDeclaration());
        static::assertTrue($mandate->isMandateMissing());
        static::assertFalse($mandate->isDuplicateDeclaration());
    }
}
