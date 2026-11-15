<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\TransportError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use Throwable;

/**
 * Status mapping of the client on the operations that are not safe to retry (ADR 0003; "unsafe" column of the
 * lot 3 design section 8).
 *
 * One method per status family.
 *
 * @mago-expect lint:too-many-methods
 */
final class JsonApiClientUnsafeTest extends TestCase
{
    private function send(Stack $stack, Operation $operation): void
    {
        $request = match ($operation) {
            Operation::ActivateCesuPlus => ApiRequest::post($operation, '/p', ['accord' => 'true'], null),
            Operation::RegisterMandate => ApiRequest::post($operation, '/p', ['numeroCesu' => '1'], null),
            Operation::CancelMandate => ApiRequest::delete($operation, '/p', ['numeroCesu' => '1']),
            Operation::RegisterEmployer,
            Operation::VerifyEmployer,
            Operation::VerifyEmployee,
            Operation::EstimateContributions,
            Operation::DeclareSalary,
            Operation::ActivateAici => ApiRequest::post($operation, '/p', [], '{}'),
        };

        $stack->jsonClient()->send(Stacks::tenant(), $stack->credentials->forTenant(Stacks::tenant()), $request);
    }

    /**
     * @param array<string, string> $headers
     */
    private function failure(Operation $operation, int $status, string $body = '{}', array $headers = []): Throwable
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, $body, $headers);
        // Only consumed by the replay of a 401.
        $stack->respondToken();
        $stack->respond($status, $body, $headers);

        try {
            $this->send($stack, $operation);
        } catch (Throwable $exception) {
            return $exception;
        }

        static::fail(\sprintf('Expected a failure for HTTP %d.', $status));
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function provideUnsafeOperations(): iterable
    {
        yield '010' => [Operation::RegisterEmployer];
        yield '050' => [Operation::DeclareSalary];
        yield '060' => [Operation::ActivateCesuPlus];
        yield '070' => [Operation::ActivateAici];
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function provideSafeWrites(): iterable
    {
        yield '030' => [Operation::RegisterMandate];
        yield '031' => [Operation::CancelMandate];
    }

    /**
     * @return iterable<string, array{Operation, int}>
     */
    public static function provideUnknownOutcomes(): iterable
    {
        foreach (self::provideUnsafeOperations() as $name => [$operation]) {
            foreach ([500, 501, 502, 503, 504, 505, 511, 513, 599] as $status) {
                yield $name . ' HTTP ' . $status => [$operation, $status];
            }
        }
    }

    #[DataProvider('provideUnknownOutcomes')]
    public function testAnyFiveHundredOtherThan512IsAnUnknownOutcome(Operation $operation, int $status): void
    {
        $exception = $this->failure($operation, $status, Fixtures::load('error_technical'), ['Retry-After' => '9']);

        static::assertInstanceOf(OutcomeUnknown::class, $exception);
        static::assertSame($operation, $exception->operation());
        static::assertSame($status, $exception->httpStatus());
        static::assertSame(['ERR_TECHNIQUE'], $exception->errors()->codes());
        $cause = $exception->getPrevious();
        static::assertInstanceOf(UrssafUnavailable::class, $cause);
        static::assertSame($status, $cause->httpStatus());
        static::assertSame(9, $cause->retryAfterSeconds());
        static::assertTrue($cause->mayHaveReachedUrssaf());
    }

    #[DataProvider('provideUnsafeOperations')]
    public function testATransportErrorIsAnUnknownOutcomeWithASanitisedCause(Operation $operation): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout for https://h/employeurs?nir=FICTIVE'));

        try {
            $this->send($stack, $operation);
            static::fail('Expected an unknown outcome.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame($operation, $exception->operation());
            static::assertNull($exception->httpStatus());
            $unavailable = $exception->getPrevious();
            static::assertInstanceOf(UrssafUnavailable::class, $unavailable);
            $previous = $unavailable->getPrevious();
            static::assertInstanceOf(TransportError::class, $previous);
            static::assertStringNotContainsString('FICTIVE', $previous->getMessage());
        }
    }

    #[DataProvider('provideUnsafeOperations')]
    public function testA429IsAnUnavailableQuotaNotAnUnknownOutcome(Operation $operation): void
    {
        $exception = $this->failure($operation, 429, '{}', ['Retry-After' => '30']);

        static::assertInstanceOf(UrssafUnavailable::class, $exception);
        static::assertTrue($exception->isQuotaExceeded());
        static::assertSame(30, $exception->retryAfterSeconds());
        static::assertFalse($exception->mayHaveReachedUrssaf());
    }

    #[DataProvider('provideUnsafeOperations')]
    public function testThe512IsAnAuthenticationFailure(Operation $operation): void
    {
        $exception = $this->failure($operation, 512);

        static::assertInstanceOf(UrssafAuthenticationFailed::class, $exception);
        static::assertSame(512, $exception->httpStatus());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNotProcessedStatuses(): iterable
    {
        yield 'just below the 5xx range' => [499];
        yield 'informational' => [199];
        yield 'redirect' => [302];
        yield 'not found' => [404];
        yield 'teapot' => [418];
    }

    #[DataProvider('provideNotProcessedStatuses')]
    public function testOtherStatusesAreUnexpectedAndNotUnknown(int $status): void
    {
        $exception = $this->failure(Operation::DeclareSalary, $status);

        static::assertInstanceOf(UnexpectedUrssafResponse::class, $exception);
        static::assertSame($status, $exception->getCode());
    }

    public function testA400IsARejectionClassifiedForTheOperation(): void
    {
        $exception = $this->failure(Operation::RegisterEmployer, 400, Fixtures::load('error_err_metier_iban'));

        static::assertInstanceOf(UrssafRequestRejected::class, $exception);
        static::assertTrue($exception->is(BusinessError::IbanInvalid));
    }

    public function testTokenFailuresNeverBecomeAnUnknownOutcome(): void
    {
        foreach ([Operation::RegisterEmployer, Operation::DeclareSalary, Operation::ActivateCesuPlus, Operation::ActivateAici] as $operation) {
            $stack = Stacks::sandbox();
            $stack->respond(500, '');
            try {
                $this->send($stack, $operation);
                static::fail('Expected an unavailable exception.');
            } catch (UrssafUnavailable $exception) {
                static::assertSame(500, $exception->httpStatus());
                static::assertFalse($exception->mayHaveReachedUrssaf());
                static::assertSame($operation, $exception->operation());
            }
            static::assertCount(1, $stack->requests());

            $transport = Stacks::sandbox();
            $transport->fail(new TransportFailure('no route'));
            try {
                $this->send($transport, $operation);
                static::fail('Expected an unavailable exception.');
            } catch (UrssafUnavailable $exception) {
                static::assertNull($exception->httpStatus());
                static::assertFalse($exception->mayHaveReachedUrssaf());
                static::assertInstanceOf(TransportError::class, $exception->getPrevious());
            }
        }
    }

    #[DataProvider('provideSafeWrites')]
    public function testSafeWritesKeepTheRetryableAndUnexpectedStatuses(Operation $operation): void
    {
        foreach ([500, 502, 503, 504] as $status) {
            $exception = $this->failure($operation, $status);
            static::assertInstanceOf(UrssafUnavailable::class, $exception);
            static::assertSame($status, $exception->httpStatus());
        }
        foreach ([501, 505, 599, 404] as $status) {
            static::assertInstanceOf(UnexpectedUrssafResponse::class, $this->failure($operation, $status));
        }

        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout'));
        try {
            $this->send($stack, $operation);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertTrue($exception->mayHaveReachedUrssaf());
        }
    }
}
