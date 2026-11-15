<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain;

use Closure;
use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * Assertion helpers shared by every domain test.
 */
final class DomainAssertions
{
    /**
     * Runs the action and asserts it throws a domain exception with the given violation code.
     *
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    public static function assertViolationCode(string $expectedCode, Closure $action): DomainException
    {
        try {
            $action();
        } catch (DomainException $exception) {
            Assert::assertSame($expectedCode, $exception->violationCode());

            return $exception;
        }
        Assert::fail(\sprintf('Expected a domain exception with code "%s".', $expectedCode));
    }

    /**
     * Compact representation of a validation result for assertions: "<severity> <path> <code>", in order.
     *
     * @return list<string>
     */
    public static function summary(ValidationResult $result): array
    {
        return array_map(
            static fn(Violation $violation): string => \sprintf(
                '%s %s %s',
                $violation->severity()->value,
                $violation->path(),
                $violation->code(),
            ),
            $result->all(),
        );
    }
}
