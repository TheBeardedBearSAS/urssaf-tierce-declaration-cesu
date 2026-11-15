<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class ApiErrorsTest extends TestCase
{
    public function testApiError(): void
    {
        $error = ApiError::of('ERR_METIER', 'Erreur métier.', 'Veuillez indiquer un IBAN valide.');

        static::assertSame('ERR_METIER', $error->code());
        static::assertSame('Erreur métier.', $error->message());
        static::assertSame('Veuillez indiquer un IBAN valide.', $error->description());
        static::assertTrue($error->hasCode('ERR_METIER'));
        static::assertFalse($error->hasCode('ERR_METIER2'));
        static::assertFalse($error->hasCode('err_metier'));
    }

    public function testApiErrorWithoutDescription(): void
    {
        static::assertNull(ApiError::of('MANDAT_INEXISTANT', 'Mandat inexistant.', null)->description());
    }

    public function testApiErrorEquality(): void
    {
        $error = ApiError::of('ERR_METIER', 'Erreur métier.', 'd');

        static::assertTrue($error->equals(ApiError::of('ERR_METIER', 'Erreur métier.', 'd')));
        static::assertFalse($error->equals(ApiError::of('X', 'Erreur métier.', 'd')));
        static::assertFalse($error->equals(ApiError::of('ERR_METIER', 'X', 'd')));
        static::assertFalse($error->equals(ApiError::of('ERR_METIER', 'Erreur métier.', 'X')));
        static::assertFalse($error->equals(ApiError::of('ERR_METIER', 'Erreur métier.', null)));
        static::assertTrue(ApiError::of('A', 'm', null)->equals(ApiError::of('A', 'm', null)));
    }

    public function testBlankCodesAreRefused(): void
    {
        foreach (['', ' ', "\t"] as $code) {
            $exception = DomainAssertions::assertViolationCode('api_error.empty_code', static fn(): ApiError => ApiError::of($code, 'm', null));
            static::assertInstanceOf(InvariantViolation::class, $exception);
            static::assertSame('An URSSAF error must carry a code.', $exception->getMessage());
        }
    }

    public function testToSubmissionError(): void
    {
        $submissionError = ApiError::of('ERR_METIER', 'm', 'd')->toSubmissionError();

        static::assertSame('ERR_METIER', $submissionError->code());
        static::assertSame('m', $submissionError->message());
        static::assertSame('d', $submissionError->description());
        static::assertNull(ApiError::of('ERR_METIER', 'm', null)->toSubmissionError()->description());
    }

    public function testCollection(): void
    {
        $first = ApiError::of('ERR_METIER', 'm', 'd');
        $second = ApiError::of('PARAM_INVALIDE', 'm', null);

        $errors = ApiErrors::of($first, $second);

        static::assertFalse($errors->isEmpty());
        static::assertCount(2, $errors);
        static::assertSame([$first, $second], $errors->all());
        static::assertSame(['ERR_METIER', 'PARAM_INVALIDE'], $errors->codes());
        static::assertTrue($errors->hasCode('PARAM_INVALIDE'));
        static::assertTrue($errors->hasCode('ERR_METIER'));
        static::assertFalse($errors->hasCode('ERR_TECHNIQUE'));
        static::assertSame($first, $errors->first());
    }

    public function testEmptyCollections(): void
    {
        static::assertTrue(ApiErrors::none()->isEmpty());
        static::assertCount(0, ApiErrors::none());
        static::assertSame([], ApiErrors::none()->all());
        static::assertSame([], ApiErrors::none()->codes());
        static::assertSame([], ApiErrors::none()->toSubmissionErrors());
        static::assertNull(ApiErrors::none()->first());
        static::assertTrue(ApiErrors::of()->isEmpty());
        static::assertFalse(ApiErrors::none()->hasCode('X'));
    }

    public function testOfNormalisesKeys(): void
    {
        $error = ApiError::of('X', 'm', null);

        static::assertSame([$error], ApiErrors::of(...['named' => $error])->all());
        static::assertSame($error, ApiErrors::of(...['named' => $error])->first());
    }

    public function testToSubmissionErrorsKeepsTheOrder(): void
    {
        $errors = ApiErrors::of(ApiError::of('B', 'mb', 'db'), ApiError::of('A', 'ma', null));

        $submissionErrors = $errors->toSubmissionErrors();

        static::assertCount(2, $submissionErrors);
        static::assertContainsOnlyInstancesOf(SubmissionError::class, $submissionErrors);
        static::assertSame('B', $submissionErrors[0]->code());
        static::assertSame('db', $submissionErrors[0]->description());
        static::assertSame('A', $submissionErrors[1]->code());
        static::assertNull($submissionErrors[1]->description());
    }
}
