<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Exception;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

final class UnexpectedUrssafResponseTest extends TestCase
{
    public function testInvalidPayloadIsAnUrssafExceptionAndNotRetryable(): void
    {
        $exception = UnexpectedUrssafResponse::invalidPayload('field "x" is missing');

        static::assertContains(UrssafException::class, class_implements($exception));
        static::assertNotContains(RetryableException::class, class_implements($exception));
        static::assertSame('Unexpected URSSAF response: field "x" is missing.', $exception->getMessage());
        static::assertNull($exception->getPrevious());
        static::assertSame(0, $exception->getCode());
    }

    public function testCausedByKeepsThePreviousException(): void
    {
        $previous = new LogicException('boom');
        $exception = UnexpectedUrssafResponse::causedBy('bad value', $previous);

        static::assertSame($previous, $exception->getPrevious());
        static::assertSame('Unexpected URSSAF response: bad value.', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
    }

    public function testUnexpectedStatusNamesTheMethodAndTheStatus(): void
    {
        $exception = UnexpectedUrssafResponse::unexpectedStatus(Operation::DeclareSalary, 418);

        static::assertSame('Unexpected URSSAF response for method 050: HTTP 418.', $exception->getMessage());
        static::assertSame(418, $exception->getCode());
        static::assertNull($exception->getPrevious());
    }
}
