<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;

/**
 * One method per behaviour of the gateway exceptions.
 *
 * @mago-expect lint:too-many-methods
 */
final class GatewayExceptionsTest extends TestCase
{
    public function testRequestRejected(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_METIER', 'm', 'd'), ApiError::of('PARAM_INVALIDE', 'm', null));

        $exception = UrssafRequestRejected::withErrors(Operation::RegisterEmployer, 400, $errors, ErrorKind::Business, BusinessError::IbanInvalid);

        static::assertSame('URSSAF method 010 rejected the request (HTTP 400): ERR_METIER, PARAM_INVALIDE.', $exception->getMessage());
        static::assertSame(Operation::RegisterEmployer, $exception->operation());
        static::assertSame(400, $exception->httpStatus());
        static::assertSame(400, $exception->getCode());
        static::assertSame($errors, $exception->errors());
        static::assertTrue($exception->hasErrorCode('PARAM_INVALIDE'));
        static::assertFalse($exception->hasErrorCode('ERR_TECHNIQUE'));
        static::assertSame(ErrorKind::Business, $exception->kind());
        static::assertSame(BusinessError::IbanInvalid, $exception->businessError());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testRequestRejectedMessageNeverCarriesADescription(): void
    {
        $errors = ApiErrors::of(ApiError::of('PARAM_INVALIDE', 'La valeur 1990-11-256 est incorrecte', 'La valeur 1990-11-256 est incorrecte'));

        $exception = UrssafRequestRejected::withErrors(Operation::VerifyEmployer, 400, $errors, ErrorKind::InvalidParameter, null);

        static::assertStringNotContainsString('1990', $exception->getMessage());
    }

    public function testRequestRejectedWithoutErrors(): void
    {
        $exception = UrssafRequestRejected::withErrors(Operation::EstimateContributions, 400, ApiErrors::none(), ErrorKind::Unknown, null);

        static::assertSame('URSSAF method 040 rejected the request (HTTP 400): no error code.', $exception->getMessage());
        static::assertNull($exception->businessError());
    }

    /**
     * @return iterable<string, array{ErrorKind, ?BusinessError, list<string>}>
     */
    public static function provideClassifications(): iterable
    {
        yield 'duplicate' => [ErrorKind::Business, BusinessError::DuplicateDeclaration, ['isDuplicateDeclaration']];
        yield 'email' => [ErrorKind::Business, BusinessError::EmailAlreadyKnown, ['isEmailAlreadyKnown']];
        yield 'deadline' => [ErrorKind::Business, BusinessError::CesuPlusDeadlineExceeded, ['isCesuPlusDeadlineExceeded']];
        yield 'other business' => [ErrorKind::Business, BusinessError::IbanInvalid, []];
        yield 'business unclassified' => [ErrorKind::Business, null, []];
        yield 'mandate missing' => [ErrorKind::MandateMissing, null, ['isMandateMissing']];
        yield 'held elsewhere' => [ErrorKind::MandateHeldElsewhere, null, ['isMandateHeldElsewhere']];
        yield 'account unknown' => [ErrorKind::AccountUnknown, null, ['isAccountUnknown']];
        yield 'invalid parameter' => [ErrorKind::InvalidParameter, null, ['isInvalidParameter']];
        yield 'technical' => [ErrorKind::Technical, null, []];
    }

    /**
     * @param list<string> $expectedTrue
     */
    #[DataProvider('provideClassifications')]
    public function testRequestRejectedPredicates(ErrorKind $kind, ?BusinessError $businessError, array $expectedTrue): void
    {
        $exception = UrssafRequestRejected::withErrors(Operation::DeclareSalary, 400, ApiErrors::none(), $kind, $businessError);

        $actual = [
            'isDuplicateDeclaration' => $exception->isDuplicateDeclaration(),
            'isEmailAlreadyKnown' => $exception->isEmailAlreadyKnown(),
            'isCesuPlusDeadlineExceeded' => $exception->isCesuPlusDeadlineExceeded(),
            'isMandateMissing' => $exception->isMandateMissing(),
            'isMandateHeldElsewhere' => $exception->isMandateHeldElsewhere(),
            'isAccountUnknown' => $exception->isAccountUnknown(),
            'isInvalidParameter' => $exception->isInvalidParameter(),
        ];
        $expected = array_fill_keys(array_keys($actual), false);
        foreach ($expectedTrue as $name) {
            $expected[$name] = true;
        }

        static::assertSame($expected, $actual);
    }

    public function testRequestRejectedIs(): void
    {
        $exception = UrssafRequestRejected::withErrors(Operation::DeclareSalary, 400, ApiErrors::none(), ErrorKind::Business, BusinessError::UnpaidSalary);

        static::assertTrue($exception->is(BusinessError::UnpaidSalary));
        static::assertFalse($exception->is(BusinessError::UnpaidContributions));
    }

    public function testAuthenticationFailed(): void
    {
        $exception = UrssafAuthenticationFailed::withStatus(401);

        static::assertSame('URSSAF authentication or authorisation failed (HTTP 401).', $exception->getMessage());
        static::assertSame(401, $exception->httpStatus());
        static::assertSame(401, $exception->getCode());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testUnavailableWithStatus(): void
    {
        $exception = UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, 30, ApiErrors::none());

        static::assertSame('URSSAF method 050 is temporarily unavailable (HTTP 429).', $exception->getMessage());
        static::assertSame(Operation::DeclareSalary, $exception->operation());
        static::assertSame(429, $exception->httpStatus());
        static::assertSame(429, $exception->getCode());
        static::assertSame(30, $exception->retryAfterSeconds());
        static::assertTrue($exception->isQuotaExceeded());
        static::assertNull($exception->getPrevious());
        static::assertFalse($exception->mayHaveReachedUrssaf());
        static::assertTrue($exception->errors()->isEmpty());
        static::assertContains(RetryableException::class, class_implements($exception));
    }

    /**
     * @return iterable<string, array{int, bool, bool}>
     */
    public static function provideStatuses(): iterable
    {
        yield '428' => [428, false, false];
        yield '429' => [429, true, false];
        yield '430' => [430, false, false];
        yield '499' => [499, false, false];
        yield '500' => [500, false, true];
        yield '503' => [503, false, true];
    }

    #[DataProvider('provideStatuses')]
    public function testUnavailableStatusBoundaries(int $status, bool $isQuota, bool $mayHaveReached): void
    {
        $exception = UrssafUnavailable::withStatus(Operation::VerifyEmployer, $status, null, ApiErrors::none());

        static::assertSame($isQuota, $exception->isQuotaExceeded());
        static::assertSame($mayHaveReached, $exception->mayHaveReachedUrssaf());
        static::assertNull($exception->retryAfterSeconds());
    }

    public function testUnavailableKeepsTheParsedBody(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_TECHNIQUE', 'm', null));

        $exception = UrssafUnavailable::withStatus(Operation::EstimateContributions, 500, null, $errors);

        static::assertSame($errors, $exception->errors());
    }

    public function testUnavailableWhileAuthenticating(): void
    {
        $previous = new RuntimeException('Connection refused');

        $exception = UrssafUnavailable::whileAuthenticating(Operation::RegisterEmployer, 503, 12, $previous);

        static::assertSame('URSSAF authentication for method 010 is temporarily unavailable.', $exception->getMessage());
        static::assertSame(Operation::RegisterEmployer, $exception->operation());
        static::assertSame(503, $exception->httpStatus());
        static::assertSame(503, $exception->getCode());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertSame($previous, $exception->getPrevious());
        static::assertFalse($exception->mayHaveReachedUrssaf());
        static::assertTrue($exception->errors()->isEmpty());
        static::assertSame(0, UrssafUnavailable::whileAuthenticating(Operation::RegisterEmployer, null, null, null)->getCode());
        static::assertNull(UrssafUnavailable::whileAuthenticating(Operation::RegisterEmployer, null, null, null)->httpStatus());
    }

    public function testUnavailableDueToTransportError(): void
    {
        $previous = new RuntimeException('Connection refused');

        $exception = UrssafUnavailable::dueToTransportError(Operation::VerifyEmployee, $previous);

        static::assertSame('URSSAF method 021 could not be reached.', $exception->getMessage());
        static::assertSame(Operation::VerifyEmployee, $exception->operation());
        static::assertNull($exception->httpStatus());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->retryAfterSeconds());
        static::assertFalse($exception->isQuotaExceeded());
        static::assertSame($previous, $exception->getPrevious());
        static::assertTrue($exception->mayHaveReachedUrssaf());
        static::assertTrue($exception->errors()->isEmpty());
    }

    public function testOutcomeUnknownFromUnavailable(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_TECHNIQUE', 'm', null));
        $cause = UrssafUnavailable::withStatus(Operation::DeclareSalary, 500, null, $errors);

        $exception = OutcomeUnknown::fromUnavailable($cause);

        static::assertSame('The outcome of URSSAF method 050 is unknown: the call may have been processed and must not be resent automatically.', $exception->getMessage());
        static::assertSame(Operation::DeclareSalary, $exception->operation());
        static::assertSame(500, $exception->httpStatus());
        static::assertSame(500, $exception->getCode());
        static::assertSame($errors, $exception->errors());
        static::assertSame($cause, $exception->getPrevious());
        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
    }

    public function testOutcomeUnknownFromATransportError(): void
    {
        $cause = UrssafUnavailable::dueToTransportError(Operation::RegisterEmployer, new RuntimeException('timeout'));

        $exception = OutcomeUnknown::fromUnavailable($cause);

        static::assertNull($exception->httpStatus());
        static::assertSame(0, $exception->getCode());
        static::assertSame(Operation::RegisterEmployer, $exception->operation());
        static::assertTrue($exception->errors()->isEmpty());
    }

    public function testOutcomeUnknownRefusesAFailureThatDidNotReachUrssaf(): void
    {
        $cause = UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, 5, ApiErrors::none());

        try {
            $outcome = OutcomeUnknown::fromUnavailable($cause);
            static::fail('Expected exception, got ' . $outcome::class);
        } catch (InvariantViolation $exception) {
            static::assertSame('outcome_unknown.not_reached', $exception->violationCode());
            static::assertSame('Only a failure that may have reached URSSAF has an unknown outcome.', $exception->getMessage());
        }
    }

    public function testOutcomeUnknownAfterAnUnreadableResponse(): void
    {
        $cause = UrssafAuthenticationFailed::withStatus(403);

        $exception = OutcomeUnknown::afterUnreadableResponse(Operation::ActivateCesuPlus, 200, $cause);

        static::assertSame('The outcome of URSSAF method 060 is unknown: the call may have been processed and must not be resent automatically.', $exception->getMessage());
        static::assertSame(Operation::ActivateCesuPlus, $exception->operation());
        static::assertSame(200, $exception->httpStatus());
        static::assertSame(200, $exception->getCode());
        static::assertTrue($exception->errors()->isEmpty());
        static::assertSame($cause, $exception->getPrevious());
    }
}
